<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Notifications\OpportunityExpiringNotification;
use Illuminate\Console\Command;

class SendOpportunityExpiryAlerts extends Command
{
    protected $signature = 'crm:send-opportunity-expiry-alerts';

    protected $description = 'Send expiry alerts for won opportunities ending within 30 days';

    public function handle(): void
    {
        Opportunity::with(['customer.accountManager'])
            ->where('stage', 'won')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [today(), today()->addDays(30)])
            ->each(function (Opportunity $opportunity): void {
                $accountManager = $opportunity->customer?->accountManager;
                if (! $accountManager) {
                    return;
                }

                $daysUntilExpiry = (int) today()->diffInDays($opportunity->end_date);
                $accountManager->notify(new OpportunityExpiringNotification($opportunity, $daysUntilExpiry));
            });

        $this->info('Opportunity expiry alerts sent.');
    }
}
