<?php

use App\Models\Customer;
use App\Models\CustomerActivity;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list customer activities', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    CustomerActivity::factory(3)->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/activities")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('admin can create customer activity', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [
            'type'        => 'check_in',
            'subject'     => 'Quarterly check-in call',
            'description' => 'Quarterly check-in call',
            'due_date'    => now()->toDateTimeString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'check_in');
});

test('admin can update customer activity', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}", [
            'description' => 'Updated description',
        ])
        ->assertOk();
});

test('admin can delete customer activity', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('customer_activities', ['id' => $activity->id]);
});

test('admin can complete customer activity with outcome', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}/complete", [
            'outcome' => 'positive',
        ])
        ->assertOk();

    $this->assertNotNull($activity->fresh()->completed_at);
    $this->assertEquals('positive', $activity->fresh()->outcome);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on customer activities returns 401', function () {
    $customer = Customer::factory()->create();
    $this->getJson("/api/v1/customers/{$customer->id}/activities")->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create customer activity requires type and activity_date', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'subject']);
});
