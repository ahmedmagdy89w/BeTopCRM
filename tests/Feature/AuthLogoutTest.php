<?php

it('does not expose public user registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Public User',
        'email' => 'public@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

it('logs out a session-cookie authenticated user without errors', function () {
    $user = adminUser();

    // Session (stateful SPA) auth carries a TransientToken, not a database token.
    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out successfully.']);
});

it('logs out a bearer-token authenticated user and revokes the token', function () {
    $user = adminUser();
    $token = $user->createToken('api-token')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out successfully.']);

    expect($user->tokens()->count())->toBe(0);
});
