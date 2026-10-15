<?php

namespace App\Services;

use App\Mail\OneTimePasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    public function __construct(private OneTimePasswordService $oneTimePasswordService) {}

    public function sendOtp(string $email): void
    {
        $email = mb_strtolower($email);
        $user = User::where('email', $email)->where('is_active', true)->first();

        if ($user === null) {
            return;
        }

        $expiresInMinutes = (int) config('auth.passwords.users.otp_expire', 10);
        $code = $this->oneTimePasswordService->issue($email, 'password_reset', $expiresInMinutes);

        Mail::to($user)->queue(new OneTimePasswordMail($code, 'password_reset', $expiresInMinutes));
    }

    public function reset(string $email, string $otp, string $password): bool
    {
        $email = mb_strtolower($email);
        $isValid = $this->oneTimePasswordService->consume(
            $email,
            'password_reset',
            $otp,
            (int) config('auth.passwords.users.otp_max_attempts', 5),
        );

        if (! $isValid) {
            return false;
        }

        $user = User::where('email', $email)->where('is_active', true)->first();

        if ($user === null) {
            return false;
        }

        DB::transaction(function () use ($user, $password): void {
            $user->update(['password' => $password]);
            $user->tokens()->delete();
        });

        return true;
    }
}
