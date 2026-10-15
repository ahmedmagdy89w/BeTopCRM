<?php

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use App\Notifications\ActivityReminderNotification;
use Illuminate\Support\Facades\Notification;

// ── Validation ───────────────────────────────────────────────────────────────

test('follow_up activity requires due_date', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [
            'type' => 'follow_up',
            'subject' => 'Call back next week',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['due_date']);
});

test('reminder_at after due_date is rejected', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [
            'type' => 'follow_up',
            'subject' => 'Call back next week',
            'due_date' => now()->addDay()->toDateTimeString(),
            'reminder_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('reminder_at in the past is rejected', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [
            'type' => 'general',
            'subject' => 'Late reminder',
            'reminder_at' => now()->subHour()->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('updating reminder_at to the past is rejected', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}", [
            'reminder_at' => now()->subHour()->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('updating only reminder_at after existing due_date is rejected', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create([
        'customer_id' => $customer->id, 'user_id' => $admin->id,
        'due_date' => now()->addDay(),
        'reminder_at' => now()->addHours(12),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}", [
            'reminder_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('updating only due_date before existing reminder_at is rejected', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create([
        'customer_id' => $customer->id, 'user_id' => $admin->id,
        'due_date' => now()->addDays(2),
        'reminder_at' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}", [
            'due_date' => now()->addHours(12)->toDateTimeString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reminder_at']);
});

test('activity accepts channel and reminder_at', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [
            'type' => 'follow_up',
            'channel' => 'call',
            'subject' => 'Call back next week',
            'due_date' => now()->addDay()->toDateTimeString(),
            'reminder_at' => now()->addHours(12)->toDateTimeString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'follow_up')
        ->assertJsonPath('data.channel', 'call')
        ->assertJsonPath('data.is_overdue', false);
});

test('complete rejects an invalid outcome', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}/complete", [
            'outcome' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome']);
});

// ── Follow-up sync ───────────────────────────────────────────────────────────

test('scheduling a follow_up syncs customer next_follow_up_at', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id, 'next_follow_up_at' => null]);
    $due = now()->addDays(3)->startOfMinute();

    $this->actingAs($admin)
        ->postJson("/api/v1/customers/{$customer->id}/activities", [
            'type' => 'follow_up',
            'subject' => 'Renewal call',
            'due_date' => $due->toDateTimeString(),
        ])
        ->assertCreated();

    expect($customer->fresh()->next_follow_up_at->equalTo($due))->toBeTrue();
});

test('completing the last follow_up clears next_follow_up_at', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $admin->id,
        'type' => 'follow_up',
        'due_date' => now()->addDay(),
    ]);
    $customer->update(['next_follow_up_at' => now()->addDay()]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}/complete", [
            'outcome' => 'positive',
        ])
        ->assertOk();

    expect($customer->fresh()->next_follow_up_at)->toBeNull();
});

test('rescheduling resets reminder_sent_at', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $admin->id,
        'type' => 'follow_up',
        'due_date' => now()->addDay(),
        'reminder_at' => now()->addHours(12),
        'reminder_sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/v1/customers/{$customer->id}/activities/{$activity->id}", [
            'due_date' => now()->addDays(5)->toDateTimeString(),
        ])
        ->assertOk();

    expect($activity->fresh()->reminder_sent_at)->toBeNull();
});

// ── Global list ──────────────────────────────────────────────────────────────

test('admin can list all activities globally with customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    CustomerActivity::factory(2)->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/activities')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.customer.id', $customer->id);
});

test('global list filters by type and overdue status', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    CustomerActivity::factory()->create([
        'customer_id' => $customer->id, 'user_id' => $admin->id,
        'type' => 'follow_up', 'due_date' => now()->subDay(),
    ]);
    CustomerActivity::factory()->create([
        'customer_id' => $customer->id, 'user_id' => $admin->id,
        'type' => 'support', 'due_date' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/activities?type=follow_up&status=overdue')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'follow_up');
});

test('sales rep global list only shows own activities', function () {
    $rep = salesRepUser();
    $other = salesRepUser();
    $mine = Customer::factory()->create(['assigned_to' => $rep->id]);
    $theirs = Customer::factory()->create(['assigned_to' => $other->id]);
    CustomerActivity::factory()->create(['customer_id' => $mine->id, 'user_id' => $rep->id]);
    CustomerActivity::factory()->create(['customer_id' => $theirs->id, 'user_id' => $other->id]);

    $this->actingAs($rep)
        ->getJson('/api/v1/activities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer.id', $mine->id);
});

test('unauthenticated global activities request returns 401', function () {
    $this->getJson('/api/v1/activities')->assertUnauthorized();
});

// ── Reminders ────────────────────────────────────────────────────────────────

test('reminder command notifies owner once', function () {
    Notification::fake();
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $admin->id,
        'type' => 'follow_up',
        'due_date' => now()->addHour(),
        'reminder_at' => now()->subMinutes(5),
        'reminder_sent_at' => null,
    ]);

    $this->artisan('crm:send-activity-reminders')->assertSuccessful();
    $this->artisan('crm:send-activity-reminders')->assertSuccessful();

    Notification::assertSentToTimes($admin, ActivityReminderNotification::class, 1);
    expect($activity->fresh()->reminder_sent_at)->not->toBeNull();
});

test('reminder falls back to account manager when owner inactive', function () {
    Notification::fake();
    $owner = User::factory()->create(['is_active' => false]);
    $manager = managerUser();
    $customer = Customer::factory()->create(['account_manager_id' => $manager->id]);
    CustomerActivity::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'type' => 'follow_up',
        'due_date' => now()->addHour(),
        'reminder_at' => now()->subMinutes(5),
        'reminder_sent_at' => null,
    ]);

    $this->artisan('crm:send-activity-reminders')->assertSuccessful();

    Notification::assertSentTo($manager, ActivityReminderNotification::class);
});

test('activity reminder mail channel honors preference', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['account_manager_id' => $admin->id]);
    $activity = CustomerActivity::factory()->create(['customer_id' => $customer->id, 'user_id' => $admin->id]);

    $enabled = (new ActivityReminderNotification($activity))->via($admin);
    expect($enabled)->toContain('database')->toContain('mail');

    $admin->update(['notification_preferences' => ['activity_reminder' => false]]);
    $disabled = (new ActivityReminderNotification($activity))->via($admin->fresh());
    expect($disabled)->toBe(['database']);
});
