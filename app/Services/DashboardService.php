<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // Legacy endpoint kept for compatibility — now based on customers.is_lead
    public function leadStats(): array
    {
        $byStatus = DB::table('customers')
            ->whereNull('deleted_at')
            ->where('is_lead', true)
            ->selectRaw('COALESCE(lead_status, ?) as status, COUNT(*) as count, SUM(estimated_value) as total_value', ['new'])
            ->groupBy('lead_status')
            ->get()
            ->keyBy('status');

        $totalLeads = $byStatus->sum('count');
        $totalEstimatedValue = $byStatus->sum('total_value');

        $oppWonValue = DB::table('opportunities')->where('stage', 'won')->sum('amount');

        $convertedCount = DB::table('customers')->whereNull('deleted_at')->where('is_lead', false)->count();
        $conversionRate = $totalLeads + $convertedCount > 0
            ? number_format($convertedCount / ($totalLeads + $convertedCount) * 100, 2, '.', '')
            : '0.00';

        return [
            'total_leads' => (int) $totalLeads,
            'by_status' => $byStatus->map(fn ($r) => (int) $r->count),
            'total_estimated_value' => number_format((float) $totalEstimatedValue, 2, '.', ''),
            'won_value' => number_format((float) $oppWonValue, 2, '.', ''),
            'conversion_rate' => $conversionRate,
        ];
    }

    public function index(array $filters, User $user): array
    {
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subDays(30))->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?? now())->endOfDay();

        $data = [
            'pipeline' => $this->pipeline($user, $dateFrom, $dateTo),
            'customers' => $this->customers($user),
            'tasks' => $this->tasks($user),
            'revenue' => $this->revenue($user, $dateFrom, $dateTo),
        ];

        if ($user->hasRole(['admin', 'manager'])) {
            $data['top_performers'] = $this->topPerformers($dateFrom, $dateTo);
            $data['campaigns'] = $this->campaigns($dateFrom, $dateTo);
        }

        return $data;
    }

    public function activityFeed(User $user, int $perPage): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->paginate(min($perPage, 50));
    }

    private function baseCustomerQuery(User $user, bool $isLead)
    {
        $query = DB::table('customers')->whereNull('deleted_at')->where('is_lead', $isLead);

        if ($user->hasRole('sales_rep')) {
            $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('account_manager_id', $user->id));
        }

        return $query;
    }

    private function pipeline(User $user, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = $this->baseCustomerQuery($user, true);

        $byStatus = (clone $query)
            ->selectRaw('lead_status as status, COUNT(*) as count, SUM(estimated_value) as total_value')
            ->groupBy('lead_status')
            ->get()
            ->keyBy('status');

        // Overlay opportunity stages
        $oppByStage = DB::table('opportunities')
            ->selectRaw('stage, COUNT(*) as count, SUM(amount) as total_value')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $totalLeads = $byStatus->sum('count');
        $converted = (clone $query)->where('is_lead', false)->count();
        $pipelineValue = $byStatus->sum('total_value');

        $conversionRate = $totalLeads + $converted > 0
            ? number_format($converted / ($totalLeads + $converted) * 100, 2, '.', '')
            : '0.00';

        return [
            'total_leads' => (int) $totalLeads,
            'by_status' => $byStatus->mapWithKeys(fn ($r, $k) => [$k => (int) $r->count]),
            'by_opportunity_stage' => $oppByStage->mapWithKeys(fn ($r, $k) => [$k => (int) $r->count]),
            'total_estimated_value' => number_format((float) $byStatus->sum('total_value'), 2, '.', ''),
            'won_value' => number_format((float) ($oppByStage->get('won')?->total_value ?? 0), 2, '.', ''),
            'conversion_rate' => $conversionRate,
            'pipeline_value' => number_format((float) $pipelineValue, 2, '.', ''),
        ];
    }

    private function customers(User $user): array
    {
        $query = DB::table('customers')->whereNull('deleted_at')->where('is_lead', false);

        if ($user->hasRole('sales_rep')) {
            $query->where('account_manager_id', $user->id);
        }

        $total = (clone $query)->count();
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn ($r) => (int) $r->count);

        $avgHealth = (clone $query)->avg('health_score') ?? 0;
        $needingFollowUp = (clone $query)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->count();
        $revenue = (clone $query)->sum('contract_value');

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'avg_health_score' => (int) round($avgHealth),
            'needing_follow_up' => $needingFollowUp,
            'total_won_opportunity_revenue' => number_format((float) $revenue, 2, '.', ''),
        ];
    }

    private function tasks(User $user): array
    {
        $query = DB::table('tasks');

        if ($user->hasRole('sales_rep')) {
            $query->where('user_id', $user->id);
        }

        return [
            'overdue' => (clone $query)->where('status', '!=', 'completed')->where('due_date', '<', today())->count(),
            'due_today' => (clone $query)->where('status', '!=', 'completed')->whereDate('due_date', today())->count(),
            'upcoming_7_days' => (clone $query)->where('status', '!=', 'completed')->whereBetween('due_date', [today()->addDay(), today()->addDays(7)])->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'completed_this_period' => (clone $query)->where('status', 'completed')->where('completed_at', '>=', now()->subDays(30))->count(),
        ];
    }

    private function revenue(User $user, Carbon $dateFrom, Carbon $dateTo): array
    {
        $baseQuery = DB::table('opportunities')->where('stage', 'won');

        if ($user->hasRole('sales_rep')) {
            $baseQuery->whereIn('customer_id', function ($sq) use ($user) {
                $sq->select('id')->from('customers')->where('account_manager_id', $user->id);
            });
        }

        $days = max(1, $dateFrom->diffInDays($dateTo));
        $prevFrom = (clone $dateFrom)->subDays($days);
        $prevTo = (clone $dateFrom)->subSecond();

        $thisPeriod = (clone $baseQuery)->whereBetween('updated_at', [$dateFrom, $dateTo])->sum('amount');
        $prevPeriod = (clone $baseQuery)->whereBetween('updated_at', [$prevFrom, $prevTo])->sum('amount');

        $changePercent = $prevPeriod > 0
            ? number_format(($thisPeriod - $prevPeriod) / $prevPeriod * 100, 2, '.', '')
            : '0.00';

        $byMonth = DB::table('opportunities')
            ->where('stage', 'won')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, SUM(amount) as revenue, COUNT(*) as opportunities_won")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'revenue' => number_format((float) $r->revenue, 2, '.', ''),
                'opportunities_won' => (int) $r->opportunities_won,
            ])
            ->values();

        return [
            'this_period' => number_format((float) $thisPeriod, 2, '.', ''),
            'previous_period' => number_format((float) $prevPeriod, 2, '.', ''),
            'change_percent' => $changePercent,
            'by_month' => $byMonth,
        ];
    }

    private function topPerformers(Carbon $dateFrom, Carbon $dateTo): array
    {
        return DB::table('opportunities')
            ->where('stage', 'won')
            ->whereBetween('opportunities.updated_at', [$dateFrom, $dateTo])
            ->whereNotNull('opportunities.assigned_to')
            ->join('users', 'opportunities.assigned_to', '=', 'users.id')
            ->selectRaw('users.id, users.name, COUNT(*) as leads_won, SUM(opportunities.amount) as won_value')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('won_value')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'user' => ['id' => $r->id, 'name' => $r->name],
                'leads_won' => (int) $r->leads_won,
                'won_value' => number_format((float) $r->won_value, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    private function campaigns(Carbon $dateFrom, Carbon $dateTo): array
    {
        $active = DB::table('campaigns')
            ->whereIn('status', ['scheduled', 'sending'])
            ->count();

        $sent = DB::table('campaign_recipients')
            ->where('status', 'sent')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $recipients = DB::table('campaign_recipients')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'active' => $active,
            'total_sent_this_period' => $sent,
            'total_recipients' => $recipients,
        ];
    }
}
