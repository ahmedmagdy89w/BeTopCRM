<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class WebhookService
{
    public function list(): LengthAwarePaginator
    {
        return WebhookEndpoint::with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function create(array $data): WebhookEndpoint
    {
        $data['secret'] ??= Str::random(64);

        return WebhookEndpoint::create($data);
    }

    public function update(WebhookEndpoint $endpoint, array $data): WebhookEndpoint
    {
        if (empty($data['secret'])) {
            unset($data['secret']);
        }

        $endpoint->update($data);

        return $endpoint->fresh();
    }

    public function delete(WebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    public function rotateSecret(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->update(['secret' => Str::random(64)]);

        return $endpoint->fresh();
    }

    public function deliveries(WebhookEndpoint $endpoint): LengthAwarePaginator
    {
        return $endpoint->deliveries()
            ->orderBy('created_at', 'desc')
            ->paginate(25);
    }

    public function redeliver(WebhookDelivery $delivery): void
    {
        $payload = $delivery->payload;
        $payload['id'] = 'dlv_'.Str::uuid();
        $payload['timestamp'] = now()->toIso8601String();

        $redelivery = $delivery->endpoint->deliveries()->create([
            'event' => $delivery->event,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        DispatchWebhookJob::dispatch($redelivery->id);
    }
}
