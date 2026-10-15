<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CustomerHealthAlertNotification extends Notification implements ShouldQueue
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
            'title'        => 'Customer Health Alert',
            'body'         => "Customer \"{$this->customer->company_name}\" health score dropped to {$this->customer->health_score}",
            'action_url'   => "/customers/{$this->customer->id}",
            'entity_type'  => 'customer',
            'entity_id'    => $this->customer->id,
            'health_score' => $this->customer->health_score,
        ];
    }
}
