<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── Settings ──────────────────────────────────────────────────────────────────

test('user can view own settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.email', $admin->email);
});

test('user can update settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/settings', [
            'timezone'    => 'America/New_York',
            'date_format' => 'MM/DD/YYYY',
        ])
        ->assertOk()
        ->assertJsonPath('data.timezone', 'America/New_York');
});

// ── Avatar ───────────────────────────────────────────────────────────────────

test('user can upload avatar', function () {
    Storage::fake('public');

    $admin  = adminUser();
    $avatar = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->actingAs($admin)
        ->postJson('/api/v1/settings/avatar', ['avatar' => $avatar])
        ->assertOk()
        ->assertJsonStructure(['data' => ['avatar_url']]);
});

test('user can delete avatar', function () {
    Storage::fake('public');

    $admin = adminUser();

    $this->actingAs($admin)
        ->deleteJson('/api/v1/settings/avatar')
        ->assertOk();
});

// ── Change password ───────────────────────────────────────────────────────────

test('user can change password', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/settings/password', [
            'current_password'     => 'password',
            'password'             => 'newPassword123!',
            'password_confirmation' => 'newPassword123!',
        ])
        ->assertOk();
});

test('change password fails with wrong current password', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/settings/password', [
            'current_password'     => 'wrongpassword',
            'password'             => 'newPassword123!',
            'password_confirmation' => 'newPassword123!',
        ])
        ->assertUnprocessable();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on settings returns 401', function () {
    $this->getJson('/api/v1/settings')->assertUnauthorized();
});
