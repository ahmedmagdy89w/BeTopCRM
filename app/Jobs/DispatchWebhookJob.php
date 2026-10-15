<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookDestinationValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(private int $deliveryId) {}

    public function handle(WebhookDestinationValidator $destinationValidator): void
    {
        $delivery = WebhookDelivery::with('endpoint')->findOrFail($this->deliveryId);
        $endpoint = $delivery->endpoint;
        $delivery->forceFill([
            'response_status' => null,
            'response_body' => null,
        ])->save();
        $delivery->increment('attempts');

        try {
            if (! $destinationValidator->isAllowed($endpoint->url)) {
                throw new \RuntimeException(__('messages.webhook_destination_blocked'));
            }

            $payloadJson = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $signature = 'sha256='.hash_hmac('sha256', $payloadJson, $endpoint->secret);

            $response = Http::timeout(30)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'X-CRM-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->send('POST', $endpoint->url, ['body' => $payloadJson]);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 1024),
                'status' => $response->successful() ? 'success' : 'pending',
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $endpoint->forceFill([
                    'last_triggered_at' => now(),
                    'failure_count' => 0,
                ])->save();

                return;
            }

            throw new \RuntimeException(__('messages.webhook_http_failed', ['status' => $response->status()]));
        } catch (Throwable $exception) {
            $delivery->update([
                'response_body' => $delivery->response_status === null
                    ? mb_substr($exception->getMessage(), 0, 1024)
                    : $delivery->response_body,
                'status' => 'pending',
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->status === 'success') {
            return;
        }

        $delivery->update([
            'status' => 'failed',
            'response_body' => $delivery->response_body
                ?? ($exception === null ? null : mb_substr($exception->getMessage(), 0, 1024)),
        ]);

        $endpoint = $delivery->endpoint;
        $newCount = $endpoint->failure_count + 1;
        $updates = ['failure_count' => $newCount];

        if ($newCount >= 10) {
            $updates['is_active'] = false;
        }

        $endpoint->forceFill($updates)->save();
    }
}
