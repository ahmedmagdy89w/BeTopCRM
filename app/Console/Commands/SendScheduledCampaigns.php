<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:send-scheduled-campaigns')]
#[Description('Queue email jobs for campaigns whose scheduled time has arrived')]
class SendScheduledCampaigns extends Command
{
    public function handle(CampaignService $campaignService): int
    {
        Campaign::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->chunkById(100, function ($campaigns) use ($campaignService): void {
                foreach ($campaigns as $campaign) {
                    $campaignService->send($campaign);
                }
            });

        return self::SUCCESS;
    }
}
