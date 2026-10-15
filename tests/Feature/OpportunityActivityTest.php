<?php

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityActivity;

function makeOpportunity(): Opportunity
{
    $admin = adminUser();
    $customer = Customer::factory()->create(['created_by' => $admin->id]);

    return Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'created_by' => $admin->id,
    ]);
}

test('admin can list opportunity activities', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    OpportunityActivity::factory(2)->create(['opportunity_id' => $opp->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('admin can log opportunity activity', function () {
    $admin = adminUser();
    $opp = makeOpportunity();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities", [
            'type' => 'call',
            'subject' => 'Discovery call',
        ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Discovery call');

    $this->assertDatabaseHas('opportunity_activities', [
        'opportunity_id' => $opp->id,
        'subject' => 'Discovery call',
    ]);
});

test('admin can complete opportunity activity', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create(['opportunity_id' => $opp->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}/complete", [
            'outcome' => 'positive',
        ])
        ->assertOk()
        ->assertJsonPath('data.outcome', 'positive');
});

test('admin can delete opportunity activity', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create(['opportunity_id' => $opp->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}")
        ->assertNoContent();
});

test('activity for another opportunity returns 404', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $other = makeOpportunity();
    $activity = OpportunityActivity::factory()->create(['opportunity_id' => $other->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}", [
            'subject' => 'Hacked',
        ])
        ->assertNotFound();
});

test('admin can log opportunity activity with channel and reminder', function () {
    $admin = adminUser();
    $opp = makeOpportunity();

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities", [
            'type' => 'call',
            'channel' => 'call',
            'subject' => 'Follow-up call',
            'due_date' => now()->addDay()->toDateTimeString(),
            'reminder_at' => now()->addHours(2)->toDateTimeString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.channel', 'call');

    expect($response->json('data.reminder_at'))->not->toBeNull();

    $this->assertDatabaseHas('opportunity_activities', [
        'opportunity_id' => $opp->id,
        'channel' => 'call',
    ]);
});

test('reminder in the past returns 422', function () {
    $admin = adminUser();
    $opp = makeOpportunity();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities", [
            'type' => 'call',
            'subject' => 'Late reminder',
            'reminder_at' => now()->subHour()->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('reminder after due date returns 422', function () {
    $admin = adminUser();
    $opp = makeOpportunity();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities", [
            'type' => 'call',
            'subject' => 'Bad reminder',
            'due_date' => now()->addHour()->toDateTimeString(),
            'reminder_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('completing with invalid outcome returns 422', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create(['opportunity_id' => $opp->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}/complete", [
            'outcome' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome']);
});

test('rescheduling resets pending reminder', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create([
        'opportunity_id' => $opp->id,
        'user_id' => $admin->id,
        'reminder_at' => now()->addHour(),
        'reminder_sent_at' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}", [
            'due_date' => now()->addDays(3)->toDateTimeString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.reminder_sent_at', null);
});

test('activities can be filtered by type, channel and search', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    OpportunityActivity::factory()->create([
        'opportunity_id' => $opp->id, 'user_id' => $admin->id,
        'type' => 'call', 'channel' => 'call', 'subject' => 'Unique discovery subject',
    ]);
    OpportunityActivity::factory()->create([
        'opportunity_id' => $opp->id, 'user_id' => $admin->id,
        'type' => 'email', 'channel' => 'email', 'subject' => 'Other subject',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities?type=email")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject', 'Other subject');

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities?channel=call")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities?search=Unique+discovery")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('activity resource exposes overdue flag', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create([
        'opportunity_id' => $opp->id, 'user_id' => $admin->id,
        'due_date' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities?status=overdue")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_overdue', true);
});

test('unauthenticated request on opportunity activities returns 401', function () {
    $this->getJson('/api/v1/customers/1/opportunities/1/activities')->assertUnauthorized();
});

test('updating only reminder_at after existing due_date is rejected', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create([
        'opportunity_id' => $opp->id, 'user_id' => $admin->id,
        'due_date' => now()->addDay(),
        'reminder_at' => now()->addHours(12),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}", [
            'reminder_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('updating only due_date before existing reminder_at is rejected', function () {
    $admin = adminUser();
    $opp = makeOpportunity();
    $activity = OpportunityActivity::factory()->create([
        'opportunity_id' => $opp->id, 'user_id' => $admin->id,
        'due_date' => now()->addDays(2),
        'reminder_at' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$opp->customer_id}/opportunities/{$opp->id}/activities/{$activity->id}", [
            'due_date' => now()->addHours(12)->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});
