<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FollowUpReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Customer $customer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'              => 'Follow-Up Due',
            'body'               => "Customer \"{$this->customer->company_name}\" follow-up is due today",
            'action_url'         => "/customers/{$this->customer->id}",
            'entity_type'        => 'customer',
            'entity_id'          => $this->customer->id,
            'next_follow_up_at'  => $this->customer->next_follow_up_at,
        ];
    }
}
