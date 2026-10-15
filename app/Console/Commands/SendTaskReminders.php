<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueNotification;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'crm:send-task-reminders';
    protected $description = 'Send task due notifications to assigned users for tasks due today';

    public function handle(): void
    {
        Task::with('assignedTo')
            ->whereDate('due_date', today())
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('assigned_to')
            ->each(function (Task $task) {
                $task->assignedTo->notify(new TaskDueNotification($task));
            });

        $this->info('Task reminders sent.');
    }
}
