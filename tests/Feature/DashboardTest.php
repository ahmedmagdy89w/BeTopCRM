<?php

use App\Models\AuditLog;
use App\Models\Customer;

// ── Dashboard ─────────────────────────────────────────────────────────────────

test('admin can access enhanced dashboard', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin can access dashboard activity feed', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/dashboard/activity-feed')
        ->assertOk();
});

test('dashboard activity feed supports logs with null user', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);

    AuditLog::create([
        'user_id' => null,
        'auditable_type' => Customer::class,
        'auditable_id' => $customer->id,
        'action' => 'created',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/dashboard/activity-feed')
        ->assertOk();

    $items = collect($response->json('data'));
    expect($items->contains(fn ($item) => is_null($item['user'])))->toBeTrue();
});

test('admin can access lead stats', function () {
    $admin = adminUser();
    Customer::factory(2)->create(['created_by' => $admin->id, 'status' => 'new']);
    Customer::factory()->create(['created_by' => $admin->id, 'status' => 'contacted']);

    $this->actingAs($admin)
        ->getJson('/api/v1/dashboard/lead-stats')
        ->assertOk();
});

test('unauthenticated request on dashboard returns 401', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});
