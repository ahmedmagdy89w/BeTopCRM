<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Opportunity;

test('admin can list opportunities for customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    Opportunity::factory(2)->create(['customer_id' => $customer->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$customer->id}/opportunities")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('admin can create opportunity for customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities", [
            'title' => 'Big opportunity',
            'type' => 'renewal',
            'amount' => 50000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ])
        ->assertCreated()
        ->assertJsonPath('data.stage', 'prospecting')
        ->assertJsonPath('data.type', 'renewal')
        ->assertJsonPath('data.start_date', '2026-01-01')
        ->assertJsonPath('data.end_date', '2027-01-01');
});

test('admin can create opportunity with new type', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities", [
            'title' => 'New opportunity',
            'type' => 'new',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'new');
});

test('opportunity inherits settings currency when omitted', function () {
    $admin = adminUser();
    CompanySetting::setValue('company_currency', 'EUR');
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities", [
            'title' => 'Euro deal',
            'type' => 'initial',
        ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'EUR');
});

test('explicit opportunity currency is ignored in favor of settings', function () {
    $admin = adminUser();
    CompanySetting::setValue('company_currency', 'EUR');
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities", [
            'title' => 'Pound deal',
            'type' => 'initial',
            'currency' => 'GBP',
        ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'EUR');
});

test('updating opportunity cannot change currency', function () {
    $admin = adminUser();
    CompanySetting::setValue('company_currency', 'EUR');
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create(['customer_id' => $customer->id, 'created_by' => $admin->id, 'currency' => 'EUR']);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}", [
            'currency' => 'GBP',
        ])
        ->assertOk()
        ->assertJsonPath('data.currency', 'EUR');
});

test('admin can assign opportunity to user on create', function () {
    $admin = adminUser();
    $assignee = managerUser();
    $customer = Customer::factory()->lead()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities", [
            'title' => 'Assigned deal',
            'type' => 'initial',
            'assigned_to' => $assignee->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.assigned_to.id', $assignee->id)
        ->assertJsonPath('data.assigned_to.name', $assignee->name);
});

test('admin can reassign and unassign opportunity on update', function () {
    $admin = adminUser();
    $first = managerUser();
    $second = salesRepUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
        'assigned_to' => $first->id,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}", [
            'assigned_to' => $second->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.assigned_to.id', $second->id);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}", [
            'assigned_to' => null,
        ])
        ->assertOk();

    expect($opportunity->fresh()->assigned_to)->toBeNull();
});

test('global list can be filtered by created date range', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $old = Opportunity::factory()->create([
        'customer_id' => $customer->id, 'created_by' => $admin->id, 'created_at' => now()->subDays(40),
    ]);
    $recent = Opportunity::factory()->create([
        'customer_id' => $customer->id, 'created_by' => $admin->id, 'created_at' => now()->subDays(5),
    ]);

    $ids = $this->actingAs($admin)
        ->getJson('/api/v1/opportunities?date_from='.now()->subDays(30)->toDateString())
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($recent->id)->not->toContain($old->id);

    $ids = $this->actingAs($admin)
        ->getJson('/api/v1/opportunities?date_to='.now()->subDays(30)->toDateString())
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($old->id)->not->toContain($recent->id);
});

test('admin can update an opportunity contract end date', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opportunity = Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/opportunities/{$opportunity->id}", [
            'end_date' => '2027-06-30',
        ])
        ->assertOk()
        ->assertJsonPath('data.end_date', '2027-06-30');
});

test('opportunity contract end date cannot precede its start date', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/opportunities", [
            'title' => 'Invalid contract dates',
            'start_date' => '2027-01-01',
            'end_date' => '2026-12-31',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_date');
});

test('opportunity stage transitions forward', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opp = Opportunity::factory()->create(['customer_id' => $customer->id, 'stage' => 'prospecting', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/opportunities/{$opp->id}/stage", ['stage' => 'qualification'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'qualification');
});

test('admin can move an opportunity back to the previous stage', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opp = Opportunity::factory()->create(['customer_id' => $customer->id, 'stage' => 'proposal', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/opportunities/{$opp->id}/stage", ['stage' => 'qualification'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'qualification');
});

test('invalid opportunity stage transition returns 422', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);
    $opp = Opportunity::factory()->create(['customer_id' => $customer->id, 'stage' => 'prospecting', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/opportunities/{$opp->id}/stage", ['stage' => 'won'])
        ->assertUnprocessable();
});

test('unauthenticated opportunity request returns 401', function () {
    $this->getJson('/api/v1/customers/1/opportunities')->assertUnauthorized();
});
