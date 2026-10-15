<?php

use App\Models\Customer;

// ── Audit log creation ───────────────────────────────────────────────────────

test('creating a customer generates an audit log entry', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/customers', [
            'type' => 'company',
            'company_name' => 'Auditable Corp',
            'industry' => 'technology',
        ]);

    $org = Customer::where('company_name', 'Auditable Corp')->first();
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Customer::class,
        'auditable_id' => $org->id,
        'action' => 'created',
    ]);
});

test('updating a customer generates an audit log entry', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$lead->id}", ['first_name' => 'Changed']);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Customer::class,
        'auditable_id' => $lead->id,
        'action' => 'updated',
    ]);
});

test('deleting a customer generates an audit log entry', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$lead->id}");

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Customer::class,
        'auditable_id' => $lead->id,
        'action' => 'deleted',
    ]);
});

// ── List & filter ────────────────────────────────────────────────────────────

test('admin can list audit logs', function () {
    $admin = adminUser();
    Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('admin can get audit logs for a specific entity', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/audit-logs/entity/customer/{$lead->id}")
        ->assertOk();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager cannot access audit logs', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();
});

test('unauthenticated request on audit logs returns 401', function () {
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
});
