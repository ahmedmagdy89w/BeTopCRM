<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    public function dispatch(string $event, array $data): void
    {
        $endpoints = WebhookEndpoint::active()->subscribedTo($event)->get();

        foreach ($endpoints as $endpoint) {
            $payload = [
                'id' => 'dlv_'.Str::uuid(),
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ];

            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
            ]);

            DispatchWebhookJob::dispatch($delivery->id);
        }
    }
}
