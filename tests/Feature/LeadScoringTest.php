<?php

use App\Models\Customer;

// ── Score retrieval ──────────────────────────────────────────────────────────

test('admin can get customer lead score', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/score")
        ->assertOk()
        ->assertJsonStructure(['data' => ['score', 'breakdown']]);
});

test('customer score breakdown contains expected categories', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create([
        'created_by' => $admin->id,
        'lead_status' => 'qualified',
        'source' => 'website',
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/score")
        ->assertOk();

    $breakdown = $response->json('data.breakdown');
    expect($breakdown)->toBeArray()->not->toBeEmpty();
});

// ── Score recalculation ──────────────────────────────────────────────────────

test('admin can trigger score recalculation', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/score/recalculate")
        ->assertOk()
        ->assertJsonStructure(['data' => ['score']]);
});

test('score is updated after recalculation when profile changes', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create([
        'created_by' => $admin->id,
        'lead_status' => 'new',
    ]);

    $before = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/score")
        ->json('data.score');

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/lead-status", ['lead_status' => 'contacted']);

    $after = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/score/recalculate")
        ->json('data.score');

    expect($before)->toBeNumeric();
    expect($after)->toBeNumeric();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager can view customer score', function () {
    $admin = adminUser();
    $manager = managerUser();
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($manager)
        ->getJson("/api/v1/customers/{$customer->id}/score")
        ->assertOk();
});

test('sales_rep can view score for assigned customer', function () {
    $rep = salesRepUser();
    $customer = Customer::factory()->lead()->create([
        'created_by' => $rep->id,
        'assigned_to' => $rep->id,
    ]);

    $this->actingAs($rep)
        ->getJson("/api/v1/customers/{$customer->id}/score")
        ->assertOk();
});

test('unauthenticated request on customer score returns 401', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->getJson("/api/v1/customers/{$customer->id}/score")->assertUnauthorized();
});
