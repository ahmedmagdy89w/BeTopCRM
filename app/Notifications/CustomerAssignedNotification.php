<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CustomerAssignedNotification extends Notification implements ShouldQueue
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
            'title' => $this->customer->is_lead ? 'Lead Assigned' : 'Customer Assigned',
            'body' => "You have been assigned \"{$this->customer->display_name}\"",
            'action_url' => "/customers/{$this->customer->id}",
            'entity_type' => $this->customer->is_lead ? 'lead' : 'customer',
            'entity_id' => $this->customer->id,
        ];
    }
}
