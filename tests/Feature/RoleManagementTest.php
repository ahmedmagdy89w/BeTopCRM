<?php

use Spatie\Permission\Models\Role;

// ── Happy path ───────────────────────────────────────────────────────────────

test('admin can list roles', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'is_system', 'permissions']]]);
});

test('admin can list all permissions', function () {
    $this->actingAs(adminUser())
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin can create a custom role', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/roles', [
            'name' => 'support_agent',
            'permissions' => ['view-opportunities', 'view-customers'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'support_agent')
        ->assertJsonPath('data.is_system', false);

    expect(Role::where('name', 'support_agent')->exists())->toBeTrue();
});

test('admin can update a custom role permissions', function () {
    $role = Role::create(['name' => 'custom_role', 'guard_name' => 'web']);

    $this->actingAs(adminUser())
        ->putJson("/api/v1/roles/{$role->id}", [
            'permissions' => ['view-opportunities'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'custom_role');
});

test('admin can delete a custom role', function () {
    $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web']);

    $this->actingAs(adminUser())
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertNoContent();

    expect(Role::where('name', 'temp_role')->exists())->toBeFalse();
});

test('admin cannot delete a system role', function () {
    $role = Role::where('name', 'admin')->first();

    $this->actingAs(adminUser())
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertUnprocessable();
});

test('admin can create user with custom role', function () {
    Role::create(['name' => 'custom_role', 'guard_name' => 'web']);

    $this->actingAs(adminUser())
        ->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'role' => 'custom_role',
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'custom_role');
});

// ── Authorization ────────────────────────────────────────────────────────────

test('sales rep cannot list roles', function () {
    $this->actingAs(salesRepUser())
        ->getJson('/api/v1/roles')
        ->assertForbidden();
});

test('sales rep cannot create a role', function () {
    $this->actingAs(salesRepUser())
        ->postJson('/api/v1/roles', ['name' => 'hacker'])
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('creating role with duplicate name fails', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/roles', ['name' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('creating role with invalid permission fails', function () {
    $this->actingAs(adminUser())
        ->postJson('/api/v1/roles', [
            'name' => 'new_role',
            'permissions' => ['non-existent-permission'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});
