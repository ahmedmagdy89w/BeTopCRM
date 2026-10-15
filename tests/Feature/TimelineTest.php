<?php

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Note;

// ── Happy path ────────────────────────────────────────────────────────────────

test('admin can get timeline for a lead', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/timeline")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('timeline includes activity events', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $activity = CustomerActivity::factory()->create([
        'customer_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/timeline")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type')->toArray();
    expect($types)->toContain('activity');
});

test('timeline includes note events', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/timeline")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type')->toArray();
    expect($types)->toContain('note');
});

test('timeline includes audit log events', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    // Update the lead to generate an audit log entry
    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$lead->id}", ['first_name' => 'Changed']);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/timeline")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type')->toArray();
    expect($types)->toContain('audit');
});

// ── Filtering ─────────────────────────────────────────────────────────────────

test('timeline can be filtered by type', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    CustomerActivity::factory()->create(['customer_id' => $lead->id, 'user_id' => $admin->id]);
    Note::factory()->create([
        'notable_type' => 'App\\Models\\Customer',
        'notable_id' => $lead->id,
        'user_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/timeline?type=note")
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type')->unique()->toArray();
    expect($types)->toEqual(['note']);
});

test('timeline can be filtered by date range', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}/timeline?from=2020-01-01&to=2099-12-31")
        ->assertOk();
});

// ── Authorization ─────────────────────────────────────────────────────────────

test('manager can view timeline', function () {
    $admin = adminUser();
    $manager = managerUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($manager)
        ->getJson("/api/v1/customers/{$lead->id}/timeline")
        ->assertOk();
});

test('sales_rep can view timeline for assigned lead', function () {
    $rep = salesRepUser();
    $lead = Customer::factory()->create(['created_by' => $rep->id, 'assigned_to' => $rep->id]);

    $this->actingAs($rep)
        ->getJson("/api/v1/customers/{$lead->id}/timeline")
        ->assertOk();
});

test('unauthenticated request on timeline returns 401', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->getJson("/api/v1/customers/{$lead->id}/timeline")->assertUnauthorized();
});
