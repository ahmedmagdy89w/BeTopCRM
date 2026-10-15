<?php

use App\Models\Customer;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────────────────
// Granular tab/activity/transfer permission enforcement + JSON 403 shape
// ─────────────────────────────────────────────────────────────────────────────

function customerViewerWithout(string ...$exclude): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo('view-customers');

    return $user;
}

test('notes index returns JSON 403 without view-notes permission', function () {
    $user = customerViewerWithout();
    $customer = Customer::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/customers/{$customer->id}/notes")
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});

test('notes index allows user with view-notes permission', function () {
    $user = customerViewerWithout();
    $user->givePermissionTo('view-notes');
    $customer = Customer::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/customers/{$customer->id}/notes")
        ->assertOk();
});

test('timeline returns JSON 403 without view-timeline permission', function () {
    $user = customerViewerWithout();
    $customer = Customer::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/customers/{$customer->id}/timeline")
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});

test('timeline allows user with view-timeline permission', function () {
    $user = customerViewerWithout();
    $user->givePermissionTo('view-timeline');
    $customer = Customer::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/customers/{$customer->id}/timeline")
        ->assertOk();
});

test('global activities index returns 403 without view-activities permission', function () {
    $user = customerViewerWithout();

    $this->actingAs($user)
        ->getJson('/api/v1/activities')
        ->assertForbidden();
});

test('global activities index allows user with view-activities permission', function () {
    $user = customerViewerWithout();
    $user->givePermissionTo('view-activities');

    $this->actingAs($user)
        ->getJson('/api/v1/activities')
        ->assertOk();
});

test('nested activities index returns 403 without view-activities permission', function () {
    $user = customerViewerWithout();
    $customer = Customer::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/customers/{$customer->id}/activities")
        ->assertForbidden();
});

test('tasks index returns 403 without view-tasks permission', function () {
    $user = customerViewerWithout();

    $this->actingAs($user)
        ->getJson('/api/v1/tasks')
        ->assertForbidden();
});

test('export returns 403 without export-data permission', function () {
    $user = customerViewerWithout();

    $this->actingAs($user)
        ->getJson('/api/v1/export/customers')
        ->assertForbidden();
});

test('import index and template return 403 without import-data permission', function () {
    $user = customerViewerWithout();

    $this->actingAs($user)
        ->getJson('/api/v1/import')
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('/api/v1/import/customers/template')
        ->assertForbidden();
});

test('sales_rep keeps tab access with the new granular permissions', function () {
    $rep = salesRepUser();
    $customer = Customer::factory()->create(['created_by' => $rep->id, 'assigned_to' => $rep->id]);

    $this->actingAs($rep)->getJson('/api/v1/activities')->assertOk();
    $this->actingAs($rep)->getJson("/api/v1/customers/{$customer->id}/activities")->assertOk();
    $this->actingAs($rep)->getJson("/api/v1/customers/{$customer->id}/notes")->assertOk();
    $this->actingAs($rep)->getJson("/api/v1/customers/{$customer->id}/timeline")->assertOk();
    $this->actingAs($rep)->getJson('/api/v1/tasks')->assertOk();
});
