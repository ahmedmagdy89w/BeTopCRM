<?php

namespace App\Notifications;

use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OpportunityExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Opportunity $opportunity,
        private readonly int $daysUntilExpiry,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $customerName = $this->opportunity->customer->display_name
            ?? $this->opportunity->customer->company_name
            ?? '';
        $locale = $notifiable->locale ?? config('app.locale');

        return [
            'title' => __('messages.opportunity_expiring_title', [], $locale),
            'body' => __('messages.opportunity_expiring_body', [
                'opportunity' => $this->opportunity->title,
                'customer' => $customerName,
                'date' => $this->opportunity->end_date->format('Y-m-d'),
            ], $locale),
            'action_url' => "/customers/{$this->opportunity->customer_id}/opportunities/{$this->opportunity->id}",
            'entity_type' => 'opportunity',
            'entity_id' => $this->opportunity->id,
            'days_until_expiry' => $this->daysUntilExpiry,
        ];
    }
}
