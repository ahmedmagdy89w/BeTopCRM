<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestPasswordResetOtpRequest;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private PasswordResetService $passwordResetService) {}

    public function requestPasswordResetOtp(RequestPasswordResetOtpRequest $request): JsonResponse
    {
        $this->passwordResetService->sendOtp($request->string('email')->toString());

        return response()->json(['message' => __('messages.password_reset_otp_sent')]);
    }

    public function resetPasswordWithOtp(ResetPasswordOtpRequest $request): JsonResponse
    {
        $valid = $this->passwordResetService->reset(
            $request->string('email')->toString(),
            $request->string('otp')->toString(),
            $request->string('password')->toString(),
        );

        if (! $valid) {
            return response()->json([
                'message' => __('messages.invalid_password_reset_otp'),
                'errors' => ['otp' => [__('messages.invalid_password_reset_otp')]],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['message' => __('messages.password_reset_success')]);
    }

    /**
     * POST /api/v1/auth/login
     * Authenticate and return a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Stateful SPA: regenerate session to issue secure HttpOnly cookie
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Revoke all old tokens so only one session exists at a time.
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => new UserResource($user->load('roles', 'permissions')),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current Bearer token if present. Cookie-authenticated
        // sessions carry a TransientToken (no database row), so only
        // delete PersonalAccessToken instances.
        $accessToken = $request->user()?->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        // If stateful session exists, invalidate it and regenerate CSRF
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/v1/auth/me
     * Return the authenticated user's profile.
     */
    public function me(Request $request): UserResource
    {
        $user = $request->user()->load('roles', 'permissions');

        return new UserResource($user);
    }
}
