<?php

use App\Mail\OneTimePasswordMail;
use App\Models\User;
use App\Services\OneTimePasswordService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('supports independent otp purposes and consumes each code once', function () {
    $service = app(OneTimePasswordService::class);
    $code = $service->issue('user@example.com', 'email_verification');

    expect($service->consume('user@example.com', 'email_verification', $code))->toBeTrue()
        ->and($service->consume('user@example.com', 'email_verification', $code))->toBeFalse();
});

it('invalidates the previous code when an otp is resent', function () {
    $service = app(OneTimePasswordService::class);
    $firstCode = $service->issue('user@example.com', 'password_reset');
    $secondCode = $service->issue('user@example.com', 'password_reset');

    expect($service->consume('user@example.com', 'password_reset', $firstCode))->toBeFalse()
        ->and($service->consume('user@example.com', 'password_reset', $secondCode))->toBeTrue();
});

it('sends an otp without revealing whether the email exists', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/password/otp/request', [
        'email' => 'missing@example.com',
    ]);

    $response->assertOk()
        ->assertJson(['message' => __('messages.password_reset_otp_sent')]);

    Mail::assertNothingQueued();
});

it('resets an active user password with a single-use otp', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'user@example.com']);
    $user->createToken('old-session');

    $this->postJson('/api/v1/auth/password/otp/request', [
        'email' => $user->email,
    ])->assertOk();

    $otpMail = null;
    Mail::assertQueued(OneTimePasswordMail::class, function (OneTimePasswordMail $mail) use (&$otpMail): bool {
        $otpMail = $mail;

        return true;
    });

    $this->postJson('/api/v1/auth/password/otp/reset', [
        'email' => $user->email,
        'otp' => $otpMail->code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk()
        ->assertJson(['message' => __('messages.password_reset_success')]);

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/password/otp/reset', [
        'email' => $user->email,
        'otp' => $otpMail->code,
        'password' => 'another-password',
        'password_confirmation' => 'another-password',
    ])->assertUnprocessable();
});

it('rejects an invalid otp', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/v1/auth/password/otp/request', [
        'email' => $user->email,
    ])->assertOk();

    $this->postJson('/api/v1/auth/password/otp/reset', [
        'email' => $user->email,
        'otp' => '000000',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('otp');
});
