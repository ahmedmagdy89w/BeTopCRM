<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Console\Command;

class SendFollowUpReminders extends Command
{
    protected $signature = 'crm:send-follow-up-reminders';
    protected $description = 'Send follow-up reminder notifications to account managers';

    public function handle(): void
    {
        Customer::with('accountManager')
            ->whereNotNull('account_manager_id')
            ->whereDate('next_follow_up_at', '<=', today())
            ->whereNull('deleted_at')
            ->each(function (Customer $customer) {
                $customer->accountManager->notify(new FollowUpReminderNotification($customer));
            });

        $this->info('Follow-up reminders sent.');
    }
}
