<?php

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\CustomerAssignedNotification;
use App\Notifications\OpportunityExpiringNotification;
use Illuminate\Support\Facades\Notification;

// ── Notifications ─────────────────────────────────────────────────────────────

test('admin can list notifications', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('admin can get unread notification count', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonStructure(['count']);
});

test('admin can mark notification as read', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $admin->notify(new CustomerAssignedNotification($lead));
    $notification = $admin->notifications()->first();

    $this->actingAs($admin)
        ->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    $this->assertNotNull($notification->fresh()->read_at);
});

test('admin can mark all notifications as read', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $admin->notify(new CustomerAssignedNotification($lead));
    $admin->notify(new CustomerAssignedNotification($lead));

    $this->actingAs($admin)
        ->patchJson('/api/v1/notifications/read-all')
        ->assertOk();

    expect($admin->unreadNotifications()->count())->toBe(0);
});

test('admin can delete a notification', function () {
    $admin = adminUser();
    $lead = Customer::factory()->create(['created_by' => $admin->id]);
    $admin->notify(new CustomerAssignedNotification($lead));
    $notification = $admin->notifications()->first();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/notifications/{$notification->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

test('expiry alert command notifies account managers only for won opportunities with an upcoming end date', function () {
    Notification::fake();
    $manager = User::factory()->create();
    $customer = Customer::factory()->customer()->create(['account_manager_id' => $manager->id]);
    $wonOpportunity = Opportunity::factory()->won()->create([
        'customer_id' => $customer->id,
        'end_date' => today()->addDays(10),
    ]);
    Opportunity::factory()->create([
        'customer_id' => $customer->id,
        'end_date' => today()->addDays(10),
    ]);

    $this->artisan('crm:send-opportunity-expiry-alerts')->assertSuccessful();

    Notification::assertSentTo($manager, OpportunityExpiringNotification::class, function ($notification) use ($wonOpportunity) {
        $payload = $notification->toArray(User::factory()->make(['locale' => 'en']));

        return $payload['entity_type'] === 'opportunity'
            && $payload['entity_id'] === $wonOpportunity->id
            && $payload['action_url'] === "/customers/{$wonOpportunity->customer_id}/opportunities/{$wonOpportunity->id}";
    });
    Notification::assertCount(1);
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on notifications returns 401', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});
