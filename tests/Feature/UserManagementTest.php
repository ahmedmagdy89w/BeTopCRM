<?php

use App\Models\User;

// ── CRUD (happy path) ────────────────────────────────────────────────────────

test('admin can list users', function () {
    $admin = adminUser();
    User::factory(3)->create(['is_active' => true]);

    $this->actingAs($admin)
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('admin can create user', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/users', [
            'name'                  => 'New User',
            'email'                 => 'newuser@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role'                  => 'sales_rep',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'newuser@example.com');

    $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
});

test('admin can show user', function () {
    $admin = adminUser();
    $user  = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('admin can update user', function () {
    $admin = adminUser();
    $user  = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->putJson("/api/v1/users/{$user->id}", ['name' => 'Updated Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('admin can update user role', function () {
    $admin = adminUser();
    $user  = User::factory()->create(['is_active' => true]);
    $user->assignRole('sales_rep');

    $this->actingAs($admin)
        ->putJson("/api/v1/users/{$user->id}/role", ['role' => 'manager'])
        ->assertOk();

    expect($user->fresh()->hasRole('manager'))->toBeTrue();
});

test('admin can deactivate and reactivate user', function () {
    $admin = adminUser();
    $user  = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/users/{$user->id}")
        ->assertNoContent();

    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->postJson("/api/v1/users/{$user->id}/activate")
        ->assertOk();

    expect($user->fresh()->is_active)->toBeTrue();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('manager cannot manage users', function () {
    $manager = managerUser();

    $this->actingAs($manager)
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

test('sales_rep cannot manage users', function () {
    $rep = salesRepUser();

    $this->actingAs($rep)
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

test('unauthenticated request on users returns 401', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('create user requires name, email, and password', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/users', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('create user email must be unique', function () {
    $admin    = adminUser();
    $existing = User::factory()->create(['email' => 'taken@example.com', 'is_active' => true]);

    $this->actingAs($admin)
        ->postJson('/api/v1/users', [
            'name'                  => 'Dupe',
            'email'                 => 'taken@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role'                  => 'sales_rep',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
