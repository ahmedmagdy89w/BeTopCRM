<?php

use App\Models\User;

// ── GET /api/v1/users/assignable ─────────────────────────────────────────────

test('admin can search assignable users', function () {
    $admin = adminUser();
    $match = User::factory()->create(['name' => 'Zara Searchable', 'email' => 'zara@example.com', 'is_active' => true]);
    User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.com', 'is_active' => true]);

    $this->actingAs($admin)
        ->getJson('/api/v1/users/assignable?search=zara')
        ->assertOk()
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.name', 'Zara Searchable');
});

test('assignable users returns only lean fields', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/users/assignable?per_page=1')
        ->assertOk();

    $first = $response->json('data.0');
    expect(array_keys($first))->toEqualCanonicalizing(['id', 'name', 'email', 'avatar_url']);
});

test('assignable users excludes inactive users', function () {
    $admin = adminUser();
    $inactive = User::factory()->create(['name' => 'Inactive Person', 'is_active' => false]);

    $ids = $this->actingAs($admin)
        ->getJson('/api/v1/users/assignable?search=Inactive&per_page=50')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->not->toContain($inactive->id);
});

test('manager and sales rep can search assignable users', function () {
    $this->actingAs(managerUser())
        ->getJson('/api/v1/users/assignable?search=a')
        ->assertOk();

    $this->actingAs(salesRepUser())
        ->getJson('/api/v1/users/assignable?search=a')
        ->assertOk();
});

test('user without assign permission cannot search assignable users', function () {
    $plain = User::factory()->create(['is_active' => true]);

    $this->actingAs($plain)
        ->getJson('/api/v1/users/assignable')
        ->assertForbidden();
});

test('unauthenticated request to assignable users returns 401', function () {
    $this->getJson('/api/v1/users/assignable')->assertUnauthorized();
});

// ── GET /api/v1/users/assignable/{user} ──────────────────────────────────────

test('admin can fetch a single assignable user', function () {
    $admin = adminUser();
    $target = User::factory()->create(['name' => 'Single User', 'is_active' => true]);

    $this->actingAs($admin)
        ->getJson("/api/v1/users/assignable/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Single User');
});

test('sales rep can fetch a single assignable user for the edit form', function () {
    $target = User::factory()->create(['name' => 'Assignee Name', 'is_active' => true]);

    $this->actingAs(salesRepUser())
        ->getJson("/api/v1/users/assignable/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Assignee Name');
});

test('fetching an inactive assignable user returns 404', function () {
    $inactive = User::factory()->create(['is_active' => false]);

    $this->actingAs(adminUser())
        ->getJson("/api/v1/users/assignable/{$inactive->id}")
        ->assertNotFound();
});
