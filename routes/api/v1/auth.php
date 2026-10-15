<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Public — no token required
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('password/otp/request', [AuthController::class, 'requestPasswordResetOtp'])
        ->middleware('throttle:5,1')
        ->name('password.otp.request');
    Route::post('password/otp/reset', [AuthController::class, 'resetPasswordWithOtp'])
        ->middleware('throttle:10,1')
        ->name('password.otp.reset');
});

// Protected — auth:sanctum required
Route::middleware('auth:sanctum')->prefix('auth')->name('auth.')->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});
