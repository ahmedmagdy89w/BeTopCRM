<?php

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDestinationValidator;
use App\Services\WebhookDispatcher;
use App\Services\WebhookService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list webhook endpoints', function () {
    $admin = adminUser();
    WebhookEndpoint::factory(2)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/webhooks')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin can create a webhook endpoint', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['lead.created', 'lead.updated'],
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['secret']])
        ->assertJsonPath('data.url', 'https://example.com/webhook');

    $this->assertDatabaseHas('webhook_endpoints', ['url' => 'https://example.com/webhook']);
    $endpoint = WebhookEndpoint::query()->where('url', 'https://example.com/webhook')->firstOrFail();
    expect($endpoint->secret)->toHaveLength(64);
    expect($endpoint->getRawOriginal('secret'))->not->toBe($endpoint->secret);
});

test('admin can create a webhook with a user supplied secret without returning it', function () {
    $admin = adminUser();
    $secret = str_repeat('custom-key-', 4);

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['customer.converted', 'opportunity.stage_changed'],
            'secret' => $secret,
        ])
        ->assertCreated()
        ->assertJsonMissingPath('data.secret');

    $endpoint = WebhookEndpoint::findOrFail($response->json('data.id'));
    expect($endpoint->secret)->toBe($secret);
    expect($endpoint->getRawOriginal('secret'))->not->toBe($secret);
});

test('webhook destinations cannot target private addresses', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://127.0.0.1/webhook',
            'events' => ['task.completed'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

test('webhook URL validation is localized in Arabic', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->withHeaders(['Accept-Language' => 'ar'])
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://127.0.0.1/webhook',
            'events' => ['task.completed'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0', 'يجب أن يستخدم رابط الويب هوك HTTPS وأن يشير حصراً إلى عناوين IP عامة.');
});

test('dispatcher persists a delivery before queueing it', function () {
    Queue::fake();
    $endpoint = WebhookEndpoint::factory()->create(['events' => ['customer.converted']]);

    app(WebhookDispatcher::class)->dispatch('customer.converted', ['id' => 42]);

    $delivery = WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->id)->firstOrFail();
    expect($delivery->status)->toBe('pending');
    expect($delivery->attempts)->toBe(0);
    Queue::assertPushed(DispatchWebhookJob::class, 1);
});

test('manual redelivery creates a separate delivery record', function () {
    Queue::fake();
    $endpoint = WebhookEndpoint::factory()->create();
    $delivery = WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event' => 'customer.converted',
        'payload' => ['id' => 'dlv_original', 'event' => 'customer.converted', 'data' => []],
        'status' => 'failed',
        'attempts' => 3,
    ]);

    app(WebhookService::class)->redeliver($delivery);

    expect(WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->id)->count())->toBe(2);
    expect(WebhookDelivery::query()->latest('id')->first()->attempts)->toBe(0);
    Queue::assertPushed(DispatchWebhookJob::class, 1);
});

test('final delivery failure increments endpoint failures once and disables at the threshold', function () {
    $endpoint = WebhookEndpoint::factory()->create();
    $endpoint->forceFill(['failure_count' => 9])->save();
    $delivery = WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event' => 'task.completed',
        'payload' => ['id' => 'dlv_failed', 'event' => 'task.completed', 'data' => []],
        'status' => 'pending',
        'attempts' => 3,
    ]);

    (new DispatchWebhookJob($delivery->id))->failed(new RuntimeException('delivery failed'));

    expect($delivery->fresh()->status)->toBe('failed');
    expect($endpoint->fresh()->failure_count)->toBe(10);
    expect($endpoint->fresh()->is_active)->toBeFalse();
});

test('webhook delivery signs payload and retries on the same delivery row', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'url' => 'https://93.184.216.34/webhook',
        'secret' => str_repeat('s', 40),
    ]);
    $payload = [
        'id' => 'dlv_test',
        'event' => 'customer.converted',
        'timestamp' => now()->toIso8601String(),
        'data' => ['id' => 42],
    ];
    $delivery = WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event' => $payload['event'],
        'payload' => $payload,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    $requestCount = 0;
    Http::fake(function () use (&$requestCount) {
        $requestCount++;

        return Http::response($requestCount === 1 ? 'try again' : 'ok', $requestCount === 1 ? 500 : 200);
    });

    $job = new DispatchWebhookJob($delivery->id);
    try {
        $job->handle(app(WebhookDestinationValidator::class));
    } catch (RuntimeException) {
    }
    $job->handle(app(WebhookDestinationValidator::class));

    Http::assertSent(function (Request $request) use ($endpoint): bool {
        $expected = 'sha256='.hash_hmac('sha256', $request->body(), $endpoint->secret);

        return $request->hasHeader('X-CRM-Signature', $expected);
    });

    expect($delivery->fresh()->attempts)->toBe(2);
    expect($delivery->fresh()->status)->toBe('success');
    $this->assertDatabaseCount('webhook_deliveries', 1);
});

test('admin can show a webhook endpoint', function () {
    $admin = adminUser();
    $webhook = WebhookEndpoint::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/webhooks/{$webhook->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $webhook->id);
});

test('admin can update a webhook endpoint', function () {
    $admin = adminUser();
    $webhook = WebhookEndpoint::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/webhooks/{$webhook->id}", ['events' => ['lead.deleted']])
        ->assertOk()
        ->assertJsonPath('data.events', ['lead.deleted']);
});

test('admin can delete a webhook endpoint', function () {
    $admin = adminUser();
    $webhook = WebhookEndpoint::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/webhooks/{$webhook->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $webhook->id]);
});

// ── Rotate secret ────────────────────────────────────────────────────────────

test('admin can rotate webhook secret', function () {
    $admin = adminUser();
    $webhook = WebhookEndpoint::factory()->create(['created_by' => $admin->id]);
    $old = $webhook->secret;

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/webhooks/{$webhook->id}/rotate-secret")
        ->assertOk();

    $newSecret = $response->json('data.secret');
    expect($newSecret)->not->toEqual($old);
    expect($webhook->fresh()->secret)->toEqual($newSecret);
});

// ── Deliveries ───────────────────────────────────────────────────────────────

test('admin can list webhook deliveries for an endpoint', function () {
    $admin = adminUser();
    $webhook = WebhookEndpoint::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/webhooks/{$webhook->id}/deliveries")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager cannot manage webhooks', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/webhooks')
        ->assertForbidden();
});

test('sales_rep cannot manage webhooks', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/webhooks')
        ->assertForbidden();
});

test('unauthenticated request on webhooks returns 401', function () {
    $this->getJson('/api/v1/webhooks')->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create webhook requires url and events', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/webhooks', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url', 'events']);
});

test('webhook url must use https', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/webhooks', [
            'url' => 'http://example.com/webhook',
            'events' => ['lead.created'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

test('webhook events must be a valid list', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['events.0']);
});
