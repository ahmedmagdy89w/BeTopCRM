<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'       => 'Task Due',
            'body'        => "Task \"{$this->task->title}\" is due today",
            'action_url'  => "/tasks/{$this->task->id}",
            'entity_type' => 'task',
            'entity_id'   => $this->task->id,
        ];
    }
}
