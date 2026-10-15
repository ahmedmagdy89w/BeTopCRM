<?php

namespace App\Services;

use App\Models\CampaignRecipient;
use App\Models\Customer;
use Carbon\Carbon;

class LeadScoringService
{
    public function calculate(Customer $customer): int
    {
        $breakdown = [];
        $weights = config('crm.lead_scoring.weights');

        // Profile completeness
        $breakdown['has_email'] = $customer->email ? $weights['has_email'] : 0;
        $breakdown['has_phone'] = $customer->phone ? $weights['has_phone'] : 0;
        $breakdown['has_organization'] = ($customer->company_name || ! empty($customer->contacts)) ? $weights['has_organization'] : 0;
        $breakdown['has_title'] = $customer->job_title ? $weights['has_title'] : 0;
        $breakdown['has_description'] = $customer->description ? $weights['has_description'] : 0;
        $breakdown['has_estimated_value'] = $customer->estimated_value ? $weights['has_estimated_value'] : 0;

        // Engagement — activity count (capped at 20 pts)
        $activityCount = $customer->activities()->count();
        $breakdown['activity_count'] = min($activityCount * $weights['activity_count'], 20);

        // Engagement — recency decay
        $lastActivity = $customer->activities()->latest('created_at')->first();
        $breakdown['activity_recency'] = $this->recencyScore($lastActivity?->created_at);

        // Campaign engagement
        $breakdown['email_opened'] = $this->campaignEngagement($customer, 'opened');
        $breakdown['email_clicked'] = $this->campaignEngagement($customer, 'clicked');

        // Pipeline position
        $breakdown['pipeline_position'] = $this->pipelineScore($customer->lead_status ?? '');

        $score = (int) array_sum($breakdown);

        $customer->updateQuietly([
            'score' => $score,
            'score_breakdown' => $breakdown,
            'scored_at' => now(),
        ]);

        return $score;
    }

    public function recalculateAll(): int
    {
        $count = 0;
        Customer::where('is_lead', true)->cursor()->each(function (Customer $customer) use (&$count) {
            $this->calculate($customer);
            $count++;
        });

        return $count;
    }

    public function recalculateStale(): int
    {
        $staleHours = config('crm.lead_scoring.stale_after_hours', 24);
        $count = 0;
        Customer::where('is_lead', true)->where(function ($q) use ($staleHours) {
            $q->whereNull('scored_at')
                ->orWhere('scored_at', '<', now()->subHours($staleHours));
        })->cursor()->each(function (Customer $customer) use (&$count) {
            $this->calculate($customer);
            $count++;
        });

        return $count;
    }

    private function recencyScore(?Carbon $lastActivityAt): int
    {
        if ($lastActivityAt === null) {
            return 0;
        }

        $decayDays = config('crm.lead_scoring.recency_decay_days', 30);
        $daysAgo = $lastActivityAt->diffInDays(now());
        $factor = max(0, 1 - ($daysAgo / $decayDays));

        return (int) round(config('crm.lead_scoring.weights.activity_recency') * $factor);
    }

    private function pipelineScore(string $status): int
    {
        $weights = config('crm.lead_scoring.weights');

        return match ($status) {
            'contacted' => $weights['status_contacted'],
            'qualified' => $weights['status_qualified'],
            'proposal' => $weights['status_proposal'],
            'negotiation' => $weights['status_negotiation'],
            default => 0,
        };
    }

    private function campaignEngagement(Customer $customer, string $type): int
    {
        $email = $customer->email ?? $customer->contact_email;
        if (! $email) {
            return 0;
        }

        $weights = config('crm.lead_scoring.weights');

        $hasEngagement = CampaignRecipient::where('email', $email)
            ->where('status', $type)
            ->exists();

        if ($type === 'opened') {
            return $hasEngagement ? $weights['email_opened'] : 0;
        }

        return $hasEngagement ? $weights['email_clicked'] : 0;
    }
}
