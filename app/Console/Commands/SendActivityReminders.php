<?php

namespace App\Console\Commands;

use App\Models\CustomerActivity;
use App\Notifications\ActivityReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendActivityReminders extends Command
{
    protected $signature = 'crm:send-activity-reminders';

    protected $description = 'Send due customer activity reminders to activity owners';

    public function handle(): void
    {
        $due = CustomerActivity::query()
            ->with(['user', 'customer.accountManager'])
            ->whereNull('completed_at')
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->orderBy('reminder_at')
            ->limit(200)
            ->get();

        $sent = 0;

        foreach ($due as $activity) {
            // Atomically claim the reminder so concurrent workers never double-send.
            $claimed = DB::table('customer_activities')
                ->where('id', $activity->id)
                ->whereNull('reminder_sent_at')
                ->whereNull('completed_at')
                ->update(['reminder_sent_at' => now()]);

            if (! $claimed) {
                continue;
            }

            $recipient = $activity->user;
            if (! $recipient || $recipient->is_active === false) {
                $recipient = $activity->customer?->accountManager;
            }

            if ($recipient) {
                $recipient->notify(new ActivityReminderNotification($activity->fresh(['customer', 'user'])));
                $sent++;
            } else {
                // No one to notify — release the claim so a future run can retry
                // once an owner or account manager exists.
                DB::table('customer_activities')
                    ->where('id', $activity->id)
                    ->update(['reminder_sent_at' => null]);
            }
        }

        $this->info("Activity reminders sent: {$sent}.");
    }
}
