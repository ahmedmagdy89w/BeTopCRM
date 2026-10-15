<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Opportunity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function leadPipeline(array $filters): array
    {
        $query = $this->applyLeadPipelineFilters(
            DB::table('customers')->whereNull('customers.deleted_at')->where('customers.is_lead', true),
            $filters,
        );

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total_leads')
            ->selectRaw("SUM(CASE WHEN lead_status <> 'lost' THEN 1 ELSE 0 END) as open_leads")
            ->selectRaw("SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END) as lost_leads")
            ->selectRaw('COUNT(score) as scored_leads')
            ->selectRaw('AVG(score) as avg_score')
            ->first();

        $statuses = collect(Customer::LEAD_STATUSES)
            ->mapWithKeys(fn ($status) => [$status => 0]);
        foreach ((clone $query)
            ->select('lead_status', DB::raw('COUNT(*) as lead_count'))
            ->groupBy('lead_status')
            ->get() as $row) {
            $statuses->put($row->lead_status, (int) $row->lead_count);
        }

        $bySource = (clone $query)
            ->select('source', DB::raw('COUNT(*) as lead_count'), DB::raw('AVG(score) as avg_score'))
            ->groupBy('source')
            ->orderByDesc('lead_count')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source,
                'lead_count' => (int) $row->lead_count,
                'avg_score' => $row->avg_score === null ? null : round((float) $row->avg_score, 1),
            ])
            ->values()
            ->all();

        $byOwner = (clone $query)
            ->leftJoin('users', 'customers.assigned_to', '=', 'users.id')
            ->select('users.id as user_id', 'users.name as user_name')
            ->addSelect(DB::raw('COUNT(customers.id) as lead_count'), DB::raw('AVG(customers.score) as avg_score'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('lead_count')
            ->get()
            ->map(fn ($row) => [
                'owner' => $row->user_id ? ['id' => (int) $row->user_id, 'name' => $row->user_name] : null,
                'lead_count' => (int) $row->lead_count,
                'avg_score' => $row->avg_score === null ? null : round((float) $row->avg_score, 1),
            ])
            ->values()
            ->all();

        $topScoredLeads = (clone $query)
            ->whereNotNull('score')
            ->orderByDesc('score')
            ->orderBy('id')
            ->limit(10)
            ->get([
                'id', 'company_name', 'first_name', 'last_name', 'lead_status', 'source', 'score', 'assigned_to',
            ])
            ->map(fn ($lead) => [
                'customer' => [
                    'id' => (int) $lead->id,
                    'name' => $lead->company_name ?: trim("{$lead->first_name} {$lead->last_name}"),
                ],
                'status' => $lead->lead_status,
                'source' => $lead->source,
                'score' => (int) $lead->score,
                'assigned_to' => $lead->assigned_to === null ? null : (int) $lead->assigned_to,
            ])
            ->values()
            ->all();

        return [
            'data' => [
                'by_status' => $statuses->all(),
                'by_source' => $bySource,
                'by_owner' => $byOwner,
                'top_scored_leads' => $topScoredLeads,
            ],
            'summary' => [
                'total_leads' => (int) $summary->total_leads,
                'open_leads' => (int) $summary->open_leads,
                'lost_leads' => (int) $summary->lost_leads,
                'scored_leads' => (int) $summary->scored_leads,
                'avg_score' => $summary->avg_score === null ? null : round((float) $summary->avg_score, 1),
            ],
        ];
    }

    public function productPerformance(array $filters): array
    {
        $productsQuery = DB::table('products')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.is_active',
                'product_categories.id as category_id',
                'product_categories.name as category_name'
            );

        if (! empty($filters['category_id'])) {
            $productsQuery->where('products.category_id', $filters['category_id']);
        }

        $products = $productsQuery->orderBy('products.name')->get();
        $productIds = $products->pluck('id')->all();

        $salesQuery = DB::table('opportunity_products')
            ->join('opportunities', 'opportunity_products.opportunity_id', '=', 'opportunities.id')
            ->join('products', 'opportunity_products.product_id', '=', 'products.id')
            ->where('opportunities.stage', 'won')
            ->whereIn('opportunity_products.product_id', $productIds);

        if (! empty($filters['date_from'])) {
            $salesQuery->where('opportunities.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $salesQuery->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $salesRows = $salesQuery
            ->select(
                'opportunity_products.product_id',
                'opportunities.currency',
                DB::raw('COUNT(DISTINCT opportunities.id) as opportunities_count'),
                DB::raw('SUM(opportunity_products.quantity) as quantity'),
                DB::raw('SUM(opportunity_products.quantity * COALESCE(opportunity_products.unit_price, products.unit_price)) as booked_value'),
            )
            ->groupBy('opportunity_products.product_id', 'opportunities.currency')
            ->get()
            ->groupBy('product_id');

        $interestQuery = DB::table('opportunity_products')
            ->join('opportunities', 'opportunity_products.opportunity_id', '=', 'opportunities.id')
            ->whereNotIn('opportunities.stage', ['won', 'lost'])
            ->whereIn('opportunity_products.product_id', $productIds);

        if (! empty($filters['date_from'])) {
            $interestQuery->where('opportunities.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $interestQuery->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $interestSummary = (clone $interestQuery)
            ->selectRaw('COUNT(DISTINCT opportunities.id) as opportunities_count')
            ->selectRaw('COALESCE(SUM(opportunity_products.quantity), 0) as quantity')
            ->first();

        $interestRows = $interestQuery
            ->select(
                'opportunity_products.product_id',
                DB::raw('COUNT(DISTINCT opportunities.id) as opportunities_count'),
                DB::raw('SUM(opportunity_products.quantity) as quantity'),
            )
            ->groupBy('opportunity_products.product_id')
            ->get()
            ->keyBy('product_id');

        $data = $products->map(function ($product) use ($salesRows, $interestRows) {
            $sales = collect($salesRows->get($product->id, []))
                ->map(fn ($row) => [
                    'currency' => $row->currency,
                    'opportunities_count' => (int) $row->opportunities_count,
                    'quantity' => (int) $row->quantity,
                    'booked_value' => number_format((float) $row->booked_value, 2, '.', ''),
                ])
                ->values()
                ->all();
            $interest = $interestRows->get($product->id);

            return [
                'product' => [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'is_active' => (bool) $product->is_active,
                    'category' => $product->category_id ? [
                        'id' => (int) $product->category_id,
                        'name' => $product->category_name,
                    ] : null,
                ],
                'booked_sales' => $sales,
                'open_pipeline' => [
                    'opportunities_count' => (int) ($interest->opportunities_count ?? 0),
                    'quantity' => (int) ($interest->quantity ?? 0),
                ],
            ];
        })->values();

        $bookedValues = $salesRows
            ->flatten(1)
            ->groupBy('currency')
            ->map(fn ($rows, $currency) => [
                'currency' => $currency,
                'quantity' => (int) $rows->sum('quantity'),
                'booked_value' => number_format((float) $rows->sum('booked_value'), 2, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'data' => $data->all(),
            'summary' => [
                'products' => $products->count(),
                'products_with_sales' => $data->filter(fn ($row) => $row['booked_sales'] !== [])->count(),
                'products_with_open_pipeline' => $data->filter(fn ($row) => $row['open_pipeline']['opportunities_count'] > 0)->count(),
                'open_pipeline_opportunities' => (int) $interestSummary->opportunities_count,
                'open_pipeline_quantity' => (int) $interestSummary->quantity,
                'booked_sales_by_currency' => $bookedValues,
            ],
        ];
    }

    /**
     * Per-user × per-product performance: which rep sold how much of which item.
     *
     * Supported filters: date_from, date_to (opportunities.created_at),
     * user_id (opportunities.assigned_to), product_id.
     */
    public function userProductPerformance(array $filters): array
    {
        $query = DB::table('opportunity_products')
            ->join('opportunities', 'opportunity_products.opportunity_id', '=', 'opportunities.id')
            ->join('users', 'opportunities.assigned_to', '=', 'users.id')
            ->join('products', 'opportunity_products.product_id', '=', 'products.id');

        if (! empty($filters['date_from'])) {
            $query->where('opportunities.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59');
        }
        if (! empty($filters['user_id'])) {
            $query->where('opportunities.assigned_to', $filters['user_id']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('opportunity_products.product_id', $filters['product_id']);
        }

        $grouped = (clone $query)
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'opportunities.currency',
                DB::raw('COUNT(DISTINCT opportunities.id) as opportunities_count'),
                DB::raw("SUM(CASE WHEN opportunities.stage = 'won' THEN 1 ELSE 0 END) as won_count"),
                DB::raw("SUM(CASE WHEN opportunities.stage NOT IN ('won', 'lost') THEN 1 ELSE 0 END) as open_count"),
                DB::raw('SUM(opportunity_products.quantity) as quantity'),
                DB::raw('SUM(CASE WHEN opportunities.stage = \'won\' THEN opportunity_products.quantity * COALESCE(opportunity_products.unit_price, products.unit_price) ELSE 0 END) as booked_value'),
            )
            ->groupBy('users.id', 'users.name', 'products.id', 'products.name', 'products.sku', 'opportunities.currency')
            ->orderBy('users.name')
            ->orderBy('products.name')
            ->orderBy('opportunities.currency')
            ->get();

        $data = $grouped
            ->groupBy(fn ($row) => $row->user_id.':'.$row->product_id)
            ->map(function ($lines) {
                $first = $lines->first();
                $bookedByCurrency = $lines
                    ->map(fn ($row) => [
                        'currency' => $row->currency,
                        'value' => number_format((float) $row->booked_value, 2, '.', ''),
                    ])
                    ->values()
                    ->all();

                return [
                    'user' => ['id' => (int) $first->user_id, 'name' => $first->user_name],
                    'product' => ['id' => (int) $first->product_id, 'name' => $first->product_name, 'sku' => $first->product_sku],
                    'opportunities_count' => (int) $lines->sum('opportunities_count'),
                    'won_count' => (int) $lines->sum('won_count'),
                    'open_count' => (int) $lines->sum('open_count'),
                    'quantity' => (int) $lines->sum('quantity'),
                    'booked_value_by_currency' => $bookedByCurrency,
                    'total_won_value' => $this->singleCurrencyTotal($bookedByCurrency, 'value'),
                ];
            })
            ->values()
            ->all();

        $bookedTotals = collect($data)
            ->flatMap(fn ($row) => $row['booked_value_by_currency'])
            ->groupBy('currency')
            ->map(fn ($values, $currency) => [
                'currency' => $currency,
                'value' => number_format((float) $values->sum(fn ($item) => (float) $item['value']), 2, '.', ''),
            ])
            ->values()
            ->all();

        $sumCurrency = fn (array $row) => array_sum(array_map(
            fn ($item) => (float) $item['value'],
            $row['booked_value_by_currency']
        ));
        $paged = $this->paginateReportRows($data, $filters, [
            'user' => fn (array $row) => $row['user']['name'],
            'product' => fn (array $row) => $row['product']['name'],
            'opportunities_count' => fn (array $row) => $row['opportunities_count'],
            'won_count' => fn (array $row) => $row['won_count'],
            'open_count' => fn (array $row) => $row['open_count'],
            'quantity' => fn (array $row) => $row['quantity'],
            'booked_value' => $sumCurrency,
        ]);

        return [
            'data' => $paged['data'],
            'summary' => [
                'users' => collect($data)->unique(fn ($row) => $row['user']['id'])->count(),
                'products' => collect($data)->unique(fn ($row) => $row['product']['id'])->count(),
                'booked_sales_by_currency' => $bookedTotals,
            ],
            ...(isset($paged['meta']) ? ['meta' => $paged['meta']] : []),
        ];
    }

    /**
     * Sales over time: won revenue grouped by month or year.
     *
     * Supported filters: date_from, date_to (opportunities.updated_at, like
     * the dashboard), user_id (opportunities.assigned_to),
     * group_by ('month' default or 'year').
     */
    public function salesReport(array $filters): array
    {
        $groupBy = ($filters['group_by'] ?? 'month') === 'year' ? 'year' : 'month';
        $format = $groupBy === 'year' ? '%Y' : '%Y-%m';

        $base = DB::table('opportunities');

        if (! empty($filters['date_from'])) {
            $base->where('opportunities.updated_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $base->where('opportunities.updated_at', '<=', $filters['date_to'].' 23:59:59');
        }
        if (! empty($filters['user_id'])) {
            $base->where('opportunities.assigned_to', $filters['user_id']);
        }

        $wonRows = (clone $base)
            ->where('opportunities.stage', 'won')
            ->selectRaw("DATE_FORMAT(opportunities.updated_at, '{$format}') as period")
            ->selectRaw('opportunities.currency')
            ->selectRaw('COUNT(*) as won_count')
            ->selectRaw('SUM(opportunities.amount) as revenue')
            ->groupBy('period', 'opportunities.currency')
            ->orderBy('period')
            ->orderBy('opportunities.currency')
            ->get();

        $openRows = (clone $base)
            ->whereNotIn('opportunities.stage', ['won', 'lost'])
            ->selectRaw("DATE_FORMAT(opportunities.created_at, '{$format}') as period")
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw('SUM(opportunities.amount) as open_value')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $data = $wonRows
            ->groupBy('period')
            ->map(function ($lines, $period) use ($openRows) {
                $revenueByCurrency = collect($lines)
                    ->map(fn ($row) => [
                        'currency' => $row->currency,
                        'value' => number_format((float) $row->revenue, 2, '.', ''),
                    ])
                    ->values()
                    ->all();
                $open = $openRows->get($period);

                return [
                    'period' => (string) $period,
                    'won_count' => (int) $lines->sum('won_count'),
                    'revenue_by_currency' => $revenueByCurrency,
                    'total_revenue' => $this->singleCurrencyTotal($revenueByCurrency, 'value'),
                    'open_count' => (int) ($open->open_count ?? 0),
                    'open_value' => $open && $open->open_value !== null
                        ? number_format((float) $open->open_value, 2, '.', '')
                        : '0.00',
                ];
            })
            ->values()
            ->all();

        // Periods that only have open pipeline (no wins yet) still deserve a row.
        $wonPeriods = collect($data)->map(fn ($row) => $row['period'])->all();
        foreach ($openRows as $period => $open) {
            $period = (string) $period;
            if (! in_array($period, $wonPeriods, true)) {
                $data[] = [
                    'period' => $period,
                    'won_count' => 0,
                    'revenue_by_currency' => [],
                    'total_revenue' => '0.00',
                    'open_count' => (int) $open->open_count,
                    'open_value' => $open->open_value !== null
                        ? number_format((float) $open->open_value, 2, '.', '')
                        : '0.00',
                ];
            }
        }
        usort($data, fn ($a, $b) => strcmp($a['period'], $b['period']));

        $totalByCurrency = collect($data)
            ->flatMap(fn ($row) => $row['revenue_by_currency'])
            ->groupBy('currency')
            ->map(fn ($values, $currency) => [
                'currency' => $currency,
                'value' => number_format((float) $values->sum(fn ($item) => (float) $item['value']), 2, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'data' => $data,
            'summary' => [
                'group_by' => $groupBy,
                'periods' => count($data),
                'total_won_count' => array_sum(array_column($data, 'won_count')),
                'total_revenue_by_currency' => $totalByCurrency,
            ],
        ];
    }

    public function userPerformance(array $filters): array
    {
        $oppQuery = DB::table('opportunities')
            ->join('users', 'opportunities.assigned_to', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(*) as opps_assigned'),
                DB::raw("SUM(CASE WHEN opportunities.stage = 'won' THEN 1 ELSE 0 END) as opps_won"),
                DB::raw("SUM(CASE WHEN opportunities.stage = 'lost' THEN 1 ELSE 0 END) as opps_lost"),
                DB::raw("SUM(CASE WHEN opportunities.stage NOT IN ('won', 'lost') THEN 1 ELSE 0 END) as opps_in_progress"),
                DB::raw('AVG(CASE WHEN opportunities.stage = \'won\' THEN DATEDIFF(COALESCE(opportunities.closed_at, opportunities.updated_at), opportunities.created_at) END) as avg_days_to_close')
            )
            ->groupBy('users.id', 'users.name');

        if (! empty($filters['user_id'])) {
            $oppQuery->where('opportunities.assigned_to', $filters['user_id']);
        }
        if (! empty($filters['date_from'])) {
            $oppQuery->where('opportunities.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $oppQuery->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $oppRows = $oppQuery->get()->keyBy('user_id');

        $wonValueQuery = DB::table('opportunities')
            ->where('opportunities.stage', 'won')
            ->select(
                'opportunities.assigned_to as user_id',
                'opportunities.currency',
                DB::raw('COUNT(*) as won_count'),
                DB::raw('SUM(opportunities.amount) as total_won_value'),
            )
            ->groupBy('opportunities.assigned_to', 'opportunities.currency');

        if (! empty($filters['user_id'])) {
            $wonValueQuery->where('opportunities.assigned_to', $filters['user_id']);
        }
        if (! empty($filters['date_from'])) {
            $wonValueQuery->where('opportunities.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $wonValueQuery->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $wonValues = $wonValueQuery->orderBy('opportunities.currency')->get()->groupBy('user_id');

        $leadQuery = DB::table('customers')
            ->whereNull('customers.deleted_at')
            ->where('customers.is_lead', true)
            ->whereNotNull('customers.assigned_to')
            ->select('assigned_to as user_id', DB::raw('COUNT(*) as leads_assigned'))
            ->groupBy('assigned_to');

        if (! empty($filters['user_id'])) {
            $leadQuery->where('customers.assigned_to', $filters['user_id']);
        }
        if (! empty($filters['date_from'])) {
            $leadQuery->where('customers.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $leadQuery->where('customers.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $leadRows = $leadQuery->pluck('leads_assigned', 'user_id');

        // Activities per user
        $activitiesQuery = DB::table('customer_activities')
            ->join('users', 'customer_activities.user_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                DB::raw('COUNT(*) as total'),
                'customer_activities.type'
            )
            ->groupBy('users.id', 'customer_activities.type');

        if (! empty($filters['user_id'])) {
            $activitiesQuery->where('customer_activities.user_id', $filters['user_id']);
        }
        if (! empty($filters['date_from'])) {
            $activitiesQuery->where('customer_activities.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $activitiesQuery->where('customer_activities.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $activitiesRows = $activitiesQuery->get()->groupBy('user_id');
        $userIds = $oppRows->keys()
            ->merge($leadRows->keys())
            ->merge($activitiesRows->keys())
            ->unique();
        $users = DB::table('users')
            ->whereIn('id', $userIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $data = $users->map(function ($user) use ($oppRows, $activitiesRows, $leadRows, $wonValues) {
            $row = $oppRows->get($user->id);
            $activitiesByType = collect($activitiesRows->get($user->id, []))
                ->pluck('total', 'type')
                ->map(fn ($v) => (int) $v);

            $totalActivities = $activitiesByType->sum();
            $wonCount = (int) ($row->opps_won ?? 0);
            $assignedOpportunities = (int) ($row->opps_assigned ?? 0);
            $wonValuesByCurrency = collect($wonValues->get($user->id, []))
                ->map(fn ($wonValue) => [
                    'currency' => $wonValue->currency,
                    'value' => number_format((float) $wonValue->total_won_value, 2, '.', ''),
                    'won_count' => (int) $wonValue->won_count,
                    'avg_opportunity_value' => number_format((float) $wonValue->total_won_value / max(1, (int) $wonValue->won_count), 2, '.', ''),
                ])
                ->values();
            $wonValue = $this->singleCurrencyTotal($wonValuesByCurrency->all(), 'value');
            $avgOpportunityValue = match ($wonValuesByCurrency->count()) {
                0 => '0.00',
                1 => $wonValuesByCurrency->first()['avg_opportunity_value'],
                default => null,
            };

            return [
                'user' => ['id' => $user->id, 'name' => $user->name],
                'leads_assigned' => (int) ($leadRows[$user->id] ?? 0),
                'opportunities_assigned' => $assignedOpportunities,
                'opportunities_won' => $wonCount,
                'opportunities_lost' => (int) ($row->opps_lost ?? 0),
                'opportunities_in_progress' => (int) ($row->opps_in_progress ?? 0),
                'opportunity_win_rate' => $assignedOpportunities > 0
                    ? number_format($wonCount / $assignedOpportunities * 100, 2, '.', '')
                    : '0.00',
                'leads_won' => $wonCount,
                'leads_lost' => (int) ($row->opps_lost ?? 0),
                'leads_in_progress' => (int) ($row->opps_in_progress ?? 0),
                'conversion_rate' => $assignedOpportunities > 0
                    ? number_format($wonCount / $assignedOpportunities * 100, 2, '.', '')
                    : '0.00',
                'total_won_value' => $wonValue,
                'total_won_value_by_currency' => $wonValuesByCurrency->all(),
                'avg_opportunity_value' => $avgOpportunityValue,
                'activities_logged' => $totalActivities,
                'activities_by_type' => $activitiesByType,
                'avg_days_to_close' => $row?->avg_days_to_close
                    ? round((float) $row->avg_days_to_close)
                    : null,
            ];
        })->values()->all();

        $totalAssigned = array_sum(array_column($data, 'leads_assigned'));
        $totalWon = array_sum(array_column($data, 'leads_won'));
        $totalOpportunitiesAssigned = array_sum(array_column($data, 'opportunities_assigned'));
        $totalWonValueByCurrency = collect($data)
            ->flatMap(fn ($row) => $row['total_won_value_by_currency'])
            ->groupBy('currency')
            ->map(fn ($values, $currency) => [
                'currency' => $currency,
                'value' => number_format((float) $values->sum(fn ($item) => (float) $item['value']), 2, '.', ''),
            ])
            ->values();
        $totalWonValue = $this->singleCurrencyTotal($totalWonValueByCurrency->all(), 'value');
        $opportunityWinRate = $totalOpportunitiesAssigned > 0
            ? number_format($totalWon / $totalOpportunitiesAssigned * 100, 2, '.', '')
            : '0.00';

        return [
            'data' => $data,
            'summary' => [
                'total_users' => count($data),
                'total_leads_assigned' => $totalAssigned,
                'total_opportunities_assigned' => $totalOpportunitiesAssigned,
                'total_won_value' => $totalWonValue,
                'total_won_value_by_currency' => $totalWonValueByCurrency->all(),
                'overall_opportunity_win_rate' => $opportunityWinRate,
                'overall_conversion_rate' => $opportunityWinRate,
            ],
        ];
    }

    public function companyReport(array $filters): array
    {
        $query = DB::table('customers')
            ->whereNull('customers.deleted_at')
            ->where('customers.type', 'company')
            ->leftJoin('opportunities', function ($join) use ($filters) {
                $join->on('opportunities.customer_id', '=', 'customers.id');

                if (! empty($filters['date_from'])) {
                    $join->where('opportunities.created_at', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $join->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59');
                }
            })
            ->select(
                'customers.id',
                'customers.company_name as name',
                'customers.industry',
                DB::raw('COUNT(DISTINCT opportunities.id) as total_opportunities'),
                DB::raw('COALESCE(JSON_LENGTH(customers.contacts), 0) as contacts_count')
            )
            ->selectSub(DB::table('customer_activities')
                ->selectRaw('MAX(customer_activities.created_at)')
                ->whereColumn('customer_activities.customer_id', 'customers.id'), 'latest_activity_date')
            ->groupBy('customers.id', 'customers.company_name', 'customers.industry');

        if (! empty($filters['customer_id'])) {
            $query->where('customers.id', $filters['customer_id']);
        }
        if (! empty($filters['organization_id'])) {
            $query->where('customers.id', $filters['organization_id']);
        }
        if (! empty($filters['industry'])) {
            $query->where('customers.industry', $filters['industry']);
        }
        foreach (Opportunity::STAGES as $stage) {
            $query->addSelect(DB::raw("SUM(CASE WHEN opportunities.stage = '{$stage}' THEN 1 ELSE 0 END) as stage_{$stage}"));
        }

        $rows = $query->get();

        $currencyValues = DB::table('opportunities')
            ->whereIn('opportunities.customer_id', $rows->pluck('id'))
            ->select(
                'opportunities.customer_id',
                'opportunities.currency',
                DB::raw('SUM(opportunities.amount) as estimated_value'),
                DB::raw("SUM(CASE WHEN opportunities.stage = 'won' THEN opportunities.amount ELSE 0 END) as won_value"),
            )
            ->when(! empty($filters['date_from']), fn ($query) => $query->where('opportunities.created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->where('opportunities.created_at', '<=', $filters['date_to'].' 23:59:59'))
            ->groupBy('opportunities.customer_id', 'opportunities.currency')
            ->orderBy('opportunities.currency')
            ->get()
            ->groupBy('customer_id');

        $data = $rows->map(function ($row) use ($currencyValues) {
            $total = (int) $row->total_opportunities;
            $stages = collect(Opportunity::STAGES)
                ->mapWithKeys(fn ($stage) => [$stage => (int) $row->{'stage_'.$stage}])
                ->all();
            $won = $stages['won'];
            $valuesByCurrency = collect($currencyValues->get($row->id, []))
                ->map(fn ($value) => [
                    'currency' => $value->currency,
                    'estimated_value' => number_format((float) $value->estimated_value, 2, '.', ''),
                    'won_value' => number_format((float) $value->won_value, 2, '.', ''),
                ])
                ->values()
                ->all();
            $estimatedValues = array_map(fn ($value) => [
                'currency' => $value['currency'],
                'value' => $value['estimated_value'],
            ], $valuesByCurrency);
            $wonValues = array_map(fn ($value) => [
                'currency' => $value['currency'],
                'value' => $value['won_value'],
            ], $valuesByCurrency);

            return [
                'organization' => ['id' => $row->id, 'name' => $row->name, 'industry' => $row->industry],
                'total_leads' => $total,
                'leads_by_status' => $stages,
                'conversion_rate' => $total > 0
                    ? number_format($won / $total * 100, 2, '.', '')
                    : '0.00',
                'total_estimated_value' => $this->singleCurrencyTotal($estimatedValues, 'value'),
                'total_estimated_value_by_currency' => $estimatedValues,
                'total_won_value' => $this->singleCurrencyTotal($wonValues, 'value'),
                'total_won_value_by_currency' => $wonValues,
                'contacts_count' => (int) $row->contacts_count,
                'latest_activity_date' => $row->latest_activity_date,
            ];
        })->values()->all();

        $topIndustries = collect($data)
            ->groupBy(fn ($row) => $row['organization']['industry'] ?? '')
            ->map(fn ($industryRows, $industry) => [
                'industry' => $industry,
                'won_value_by_currency' => collect($industryRows)
                    ->flatMap(fn ($row) => $row['total_won_value_by_currency'])
                    ->groupBy('currency')
                    ->map(fn ($values, $currency) => [
                        'currency' => $currency,
                        'value' => number_format((float) $values->sum(fn ($value) => (float) $value['value']), 2, '.', ''),
                    ])
                    ->values()
                    ->all(),
                'lead_count' => array_sum(array_column($industryRows->all(), 'total_leads')),
            ])
            ->map(fn ($industry) => [
                ...$industry,
                'won_value' => $this->singleCurrencyTotal(
                    array_map(fn ($value) => ['value' => $value['value']], $industry['won_value_by_currency']),
                    'value',
                ),
            ])
            ->sortByDesc('lead_count')
            ->take(3)
            ->values()
            ->all();

        $totalWonValueByCurrency = collect($data)
            ->flatMap(fn ($row) => $row['total_won_value_by_currency'])
            ->groupBy('currency')
            ->map(fn ($values, $currency) => [
                'currency' => $currency,
                'value' => number_format((float) $values->sum(fn ($item) => (float) $item['value']), 2, '.', ''),
            ])
            ->values()
            ->all();

        $sumCurrency = fn (array $row, string $key) => array_sum(array_map(
            fn ($item) => (float) $item['value'],
            $row[$key]
        ));
        $paged = $this->paginateReportRows($data, $filters, [
            'organization' => fn (array $row) => $row['organization']['name'],
            'industry' => fn (array $row) => $row['organization']['industry'],
            'total_leads' => fn (array $row) => $row['total_leads'],
            'conversion_rate' => fn (array $row) => (float) $row['conversion_rate'],
            'total_estimated_value' => fn (array $row) => $sumCurrency($row, 'total_estimated_value_by_currency'),
            'total_won_value' => fn (array $row) => $sumCurrency($row, 'total_won_value_by_currency'),
            'contacts_count' => fn (array $row) => $row['contacts_count'],
        ]);

        return [
            'data' => $paged['data'],
            'summary' => [
                'total_organizations' => count($data),
                'total_leads_across_orgs' => array_sum(array_column($data, 'total_leads')),
                'total_won_value' => $this->singleCurrencyTotal($totalWonValueByCurrency, 'value'),
                'total_won_value_by_currency' => $totalWonValueByCurrency,
                'top_industries' => $topIndustries,
            ],
            ...(isset($paged['meta']) ? ['meta' => $paged['meta']] : []),
        ];
    }

    public function customerReport(array $filters): array
    {
        $query = DB::table('customers')
            ->whereNull('customers.deleted_at')
            ->where('customers.is_lead', false)
            ->leftJoin('users as am', 'customers.account_manager_id', '=', 'am.id')
            ->leftJoin('opportunities', function ($join) {
                $join->on('opportunities.customer_id', '=', 'customers.id')
                    ->where('opportunities.stage', 'won');
            })
            ->select(
                'customers.id',
                'customers.company_name',
                'customers.first_name',
                'customers.last_name',
                'customers.status',
                'customers.health_score',
                'customers.last_contacted_at',
                'customers.next_follow_up_at',
                'customers.converted_at',
                'am.id as am_id',
                'am.name as am_name',
                DB::raw('COUNT(DISTINCT opportunities.id) as won_opportunities_count')
            )
            ->groupBy(
                'customers.id', 'customers.company_name', 'customers.first_name', 'customers.last_name',
                'customers.status', 'customers.health_score', 'customers.last_contacted_at',
                'customers.next_follow_up_at', 'customers.converted_at',
                'am.id', 'am.name'
            );

        if (! empty($filters['account_manager_id'])) {
            $query->where('customers.account_manager_id', $filters['account_manager_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customers.id', $filters['customer_id']);
        } elseif (! empty($filters['organization_id'])) {
            $query->where('customers.id', $filters['organization_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('customers.status', $filters['status']);
        }
        if (! empty($filters['type']) && in_array($filters['type'], ['individual', 'company'], true)) {
            $query->where('customers.type', $filters['type']);
        }
        if (! empty($filters['health_below'])) {
            $query->where('customers.health_score', '<', $filters['health_below']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('customers.converted_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('customers.converted_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $rows = $query->get();
        $customerIds = $rows->pluck('id')->all();

        $opportunityValues = DB::table('opportunities')
            ->whereIn('opportunities.customer_id', $customerIds)
            ->where('opportunities.stage', 'won')
            ->select('opportunities.customer_id', 'opportunities.currency', DB::raw('SUM(opportunities.amount) as total_won_value'))
            ->groupBy('opportunities.customer_id', 'opportunities.currency')
            ->orderBy('opportunities.currency')
            ->get()
            ->groupBy('customer_id');

        $activityRows = DB::table('customer_activities')
            ->whereIn('customer_id', $customerIds)
            ->select('customer_id', 'type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_id', 'type')
            ->get()
            ->groupBy('customer_id');

        $now = now();

        $data = $rows->map(function ($row) use ($activityRows, $opportunityValues, $now) {
            $activitiesByType = collect($activityRows->get($row->id, []))
                ->pluck('cnt', 'type')
                ->map(fn ($v) => (int) $v);

            $displayName = $row->company_name ?: trim("{$row->first_name} {$row->last_name}");
            $valuesByCurrency = collect($opportunityValues->get($row->id, []))
                ->map(fn ($value) => [
                    'currency' => $value->currency,
                    'value' => number_format((float) $value->total_won_value, 2, '.', ''),
                ])
                ->values()
                ->all();

            return [
                'customer' => [
                    'id' => $row->id,
                    'company_name' => $displayName,
                    'contact_name' => trim("{$row->first_name} {$row->last_name}"),
                    'status' => $row->status,
                    'health_score' => (int) $row->health_score,
                ],
                'account_manager' => ['id' => $row->am_id, 'name' => $row->am_name],
                'total_won_opportunity_value' => $this->singleCurrencyTotal($valuesByCurrency, 'value'),
                'total_won_opportunity_value_by_currency' => $valuesByCurrency,
                'won_opportunities_count' => (int) $row->won_opportunities_count,
                'activities_count' => $activitiesByType->sum(),
                'activities_by_type' => $activitiesByType,
                'last_contacted_at' => $row->last_contacted_at,
                'days_since_last_contact' => $row->last_contacted_at
                    ? (int) $now->diffInDays($row->last_contacted_at)
                    : null,
                'next_follow_up_at' => $row->next_follow_up_at,
                'days_as_customer' => $row->converted_at
                    ? (int) $now->diffInDays($row->converted_at)
                    : null,
                'converted_at' => $row->converted_at,
            ];
        })->values()->all();

        $statusSummary = collect(Customer::CUSTOMER_STATUSES)
            ->mapWithKeys(fn ($status) => [$status => 0])
            ->merge($rows->countBy('status')->map(fn ($count) => (int) $count))
            ->all();

        $totalCustomers = $rows->count();
        $churnedCount = (int) ($statusSummary['churned'] ?? 0);
        $totalRevenueByCurrency = collect($data)
            ->flatMap(fn ($row) => $row['total_won_opportunity_value_by_currency'])
            ->groupBy('currency')
            ->map(fn ($values, $currency) => [
                'currency' => $currency,
                'value' => number_format((float) $values->sum(fn ($item) => (float) $item['value']), 2, '.', ''),
            ])
            ->values()
            ->all();
        $avgHealth = $rows->avg('health_score');
        $needFollowUp = $rows->filter(fn ($row) => $row->next_follow_up_at && now()->greaterThanOrEqualTo($row->next_follow_up_at))->count();
        $totalActivities = array_sum(array_column($data, 'activities_count'));
        $churnRate = $totalCustomers > 0 ? $churnedCount / $totalCustomers * 100 : 0;

        $sumCurrency = fn (array $row) => array_sum(array_map(
            fn ($item) => (float) $item['value'],
            $row['total_won_opportunity_value_by_currency']
        ));
        $paged = $this->paginateReportRows($data, $filters, [
            'customer_name' => fn (array $row) => $row['customer']['company_name'],
            'account_manager' => fn (array $row) => $row['account_manager']['name'],
            'health_score' => fn (array $row) => $row['customer']['health_score'],
            'total_won_value' => $sumCurrency,
            'won_opportunities_count' => fn (array $row) => $row['won_opportunities_count'],
            'activities_count' => fn (array $row) => $row['activities_count'],
            'days_since_last_contact' => fn (array $row) => $row['days_since_last_contact'],
            'days_as_customer' => fn (array $row) => $row['days_as_customer'],
        ]);

        return [
            'data' => $paged['data'],
            'summary' => [
                'total_customers' => $totalCustomers,
                'by_status' => $statusSummary,
                'total_won_opportunity_revenue' => $this->singleCurrencyTotal($totalRevenueByCurrency, 'value'),
                'total_won_opportunity_revenue_by_currency' => $totalRevenueByCurrency,
                'avg_health_score' => $avgHealth ? round((float) $avgHealth) : 0,
                'customers_needing_follow_up' => $needFollowUp,
                'avg_activities_per_customer' => $totalCustomers > 0
                    ? round($totalActivities / $totalCustomers, 0)
                    : 0,
                'churn_rate' => number_format($churnRate, 2, '.', ''),
                'retention_rate' => number_format(100 - $churnRate, 2, '.', ''),
            ],
            ...(isset($paged['meta']) ? ['meta' => $paged['meta']] : []),
        ];
    }

    private function applyLeadPipelineFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['assigned_to'])) {
            $query->where('customers.assigned_to', $filters['assigned_to']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customers.id', $filters['customer_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('customers.lead_status', $filters['status']);
        }
        if (! empty($filters['source'])) {
            $query->where('customers.source', $filters['source']);
        }
        if (isset($filters['min_score'])) {
            $query->where('customers.score', '>=', $filters['min_score']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('customers.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('customers.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        return $query;
    }

    private function singleCurrencyTotal(array $values, string $valueKey): ?string
    {
        if (count($values) > 1) {
            return null;
        }

        return $values[0][$valueKey] ?? '0.00';
    }

    /**
     * Slice a fully-built report row set into a page with optional sorting.
     *
     * Only applied when the caller passes `page`/`per_page` — exports, async
     * jobs and the AI snapshot omit them and keep receiving the full dataset
     * with the report's natural ordering. Summaries are always computed over
     * the full filtered scope by the caller before slicing.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @param  array<string, callable(array<string, mixed>): int|string|null>  $sorters  sort key => value extractor
     * @return array{data: array, meta?: array{current_page: int, per_page: int, total: int}}
     */
    private function paginateReportRows(array $data, array $filters, array $sorters, ?string $defaultSort = null, string $defaultDir = 'desc'): array
    {
        if (! array_key_exists('page', $filters) && ! array_key_exists('per_page', $filters)) {
            return ['data' => $data];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 50)));

        $sortKey = is_string($filters['sort_by'] ?? null) && isset($sorters[$filters['sort_by']])
            ? $filters['sort_by']
            : $defaultSort;
        $dir = ($filters['sort_dir'] ?? $defaultDir) === 'asc' ? 'asc' : 'desc';

        if ($sortKey !== null) {
            $extract = $sorters[$sortKey];
            usort($data, function ($a, $b) use ($extract, $dir) {
                $valueA = $extract($a);
                $valueB = $extract($b);
                // Nulls always sort last, in both directions.
                if ($valueA === null) {
                    return 1;
                }
                if ($valueB === null) {
                    return -1;
                }
                $compared = is_numeric($valueA) && is_numeric($valueB)
                    ? (float) $valueA <=> (float) $valueB
                    : strcasecmp((string) $valueA, (string) $valueB);

                return $dir === 'asc' ? $compared : -$compared;
            });
        }

        return [
            'data' => array_values(array_slice($data, ($page - 1) * $perPage, $perPage)),
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => count($data)],
        ];
    }
}
