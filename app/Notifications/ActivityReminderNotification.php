<?php

namespace App\Notifications;

use App\Models\CustomerActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CustomerActivity $activity) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->preferenceEnabled($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->activity->customer;
        $due = $this->activity->due_date?->timezone($notifiable->timezone ?? 'UTC');

        return (new MailMessage)
            ->subject(__('messages.activity_reminder_subject', ['subject' => $this->activity->subject]))
            ->line(__('messages.activity_reminder_body', [
                'subject' => $this->activity->subject,
                'customer' => $customer?->display_name ?? '',
                'due' => $due?->format('Y-m-d H:i') ?? '',
            ]))
            ->action(__('messages.view_activity'), "/customers/{$this->activity->customer_id}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('messages.activity_reminder_title'),
            'body' => __('messages.activity_reminder_body', [
                'subject' => $this->activity->subject,
                'customer' => $this->activity->customer?->display_name ?? '',
                'due' => $this->activity->due_date?->format('Y-m-d H:i') ?? '',
            ]),
            'action_url' => "/customers/{$this->activity->customer_id}",
            'entity_type' => 'customer_activity',
            'entity_id' => $this->activity->id,
            'customer_id' => $this->activity->customer_id,
            'due_date' => $this->activity->due_date,
            'reminder_at' => $this->activity->reminder_at,
        ];
    }

    private function preferenceEnabled(object $notifiable): bool
    {
        $preferences = $notifiable->notification_preferences ?? [];

        return ($preferences['activity_reminder'] ?? true) === true;
    }
}
