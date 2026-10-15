<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\CampaignEmail;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public CampaignRecipient $recipient) {}

    public function handle(): void
    {
        $recipient = $this->recipient->load(['campaign.template', 'customer']);

        try {
            Mail::to($recipient->email)->send(new CampaignEmail($recipient));

            $recipient->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            // Mark campaign as sent if all recipients processed
            $campaign = $recipient->campaign;
            if ($campaign->recipients()->where('status', 'pending')->count() === 0) {
                $campaign->update(['status' => 'sent', 'sent_at' => now()]);
            }
        } catch (\Throwable $e) {
            $recipient->update([
                'status'        => 'failed',
                'failed_reason' => $e->getMessage(),
            ]);
        }
    }
}
