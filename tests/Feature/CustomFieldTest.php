<?php

use App\Models\Customer;

// ── Definitions (admin) ──────────────────────────────────────────────────────

test('admin can list custom field definitions for customers', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/custom-fields/customer')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin can create a custom field definition', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'industry_segment',
            'label' => 'Industry Segment',
            'type' => 'text',
            'is_required' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'industry_segment')
        ->assertJsonPath('data.entity_type', 'customer');

    $this->assertDatabaseHas('custom_field_definitions', ['name' => 'industry_segment']);
    $this->assertDatabaseHas('custom_field_groups', [
        'entity_type' => 'customer',
        'scope_key' => 'entity',
    ]);
});

test('a definition name cannot be duplicated within the same group', function () {
    $admin = adminUser();

    $payload = ['name' => 'duplicate_field', 'label' => 'Duplicate', 'type' => 'text'];

    $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', $payload)
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('the same name is reusable in a different model group', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/customer', [
        'name' => 'shared_name', 'label' => 'Shared', 'type' => 'text',
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/custom-fields/opportunity', [
        'name' => 'shared_name', 'label' => 'Shared', 'type' => 'text',
    ])->assertCreated();
});

test('admin can update a custom field definition', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'old_name_field',
            'label' => 'Old Name',
            'type' => 'text',
        ])
        ->assertCreated();

    $id = $response->json('data.id');

    $this->actingAs($admin)
        ->putJson("/api/v1/custom-fields/definitions/{$id}", ['label' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.label', 'New Name');
});

test('admin can delete a custom field definition', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'to_delete_field',
            'label' => 'To Delete',
            'type' => 'text',
        ])
        ->assertCreated();

    $id = $response->json('data.id');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/custom-fields/definitions/{$id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('custom_field_definitions', ['id' => $id]);
});

// ── Values on entities ───────────────────────────────────────────────────────

test('custom field value is stored and returned on a customer', function () {
    $admin = adminUser();

    // create definition first
    $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'priority_score',
            'label' => 'Priority Score',
            'type' => 'number',
        ]);

    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$lead->id}", [
            'custom_fields' => ['priority_score' => 9],
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$lead->id}")
        ->assertOk()
        ->assertJsonPath('data.custom_fields.priority_score', 9);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager cannot manage custom field definitions', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'test_mgr',
            'label' => 'Test',
            'type' => 'text',
        ])
        ->assertForbidden();
});

test('sales_rep cannot manage custom field definitions', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'test_rep',
            'label' => 'Test',
            'type' => 'text',
        ])
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create custom field requires name, label, and type', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'label', 'type']);
});

test('custom field type must be valid', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/custom-fields/customer', [
            'name' => 'bad_type',
            'label' => 'Bad Type',
            'type' => 'invalid_type',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});
