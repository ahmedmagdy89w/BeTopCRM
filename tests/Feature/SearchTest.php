<?php

use App\Models\Customer;

// ── Search ────────────────────────────────────────────────────────────────────

test('admin can search across all entity types', function () {
    $admin = adminUser();
    Customer::factory()->create(['created_by' => $admin->id, 'first_name' => 'SearchTarget', 'email' => 'st@a.com']);
    Customer::factory()->create(['type' => 'company', 'company_name' => 'SearchTarget Corp', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/search?q=SearchTarget')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('search results are grouped by entity type', function () {
    $admin = adminUser();
    Customer::factory()->create(['created_by' => $admin->id, 'first_name' => 'UniqueSearch99', 'email' => 'us99@a.com']);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/search?q=UniqueSearch99')
        ->assertOk();

    $data = $response->json('data');
    $this->assertArrayHasKey('leads', $data);
});

test('search respects permission scope for sales_rep', function () {
    $admin = adminUser();
    $rep = salesRepUser();

    // Create a lead assigned to admin (not rep)
    Customer::factory()->create(['created_by' => $admin->id, 'assigned_to' => $admin->id, 'first_name' => 'AdminOnly', 'email' => 'ao@test.com']);

    $response = $this->actingAs($rep)
        ->getJson('/api/v1/search?q=AdminOnly')
        ->assertOk();

    // sales_rep can see leads they can view (they have view-leads permission)
    // but results are empty if no leads are assigned to them
    $leads = $response->json('data.leads');
    expect(count($leads))->toBe(0);
});

test('search requires a query parameter', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/search')
        ->assertUnprocessable();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on search returns 401', function () {
    $this->getJson('/api/v1/search?q=test')->assertUnauthorized();
});
