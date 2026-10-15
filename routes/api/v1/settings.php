<?php

use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\UserSettingsController;
use Illuminate\Support\Facades\Route;

// User self-service settings + company settings
Route::middleware('auth:sanctum')->group(function () {
    // User Settings (self-service)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [UserSettingsController::class, 'show'])->name('show');
        Route::put('/', [UserSettingsController::class, 'update'])->name('update');
        Route::post('avatar', [UserSettingsController::class, 'uploadAvatar'])->name('avatar.upload');
        Route::delete('avatar', [UserSettingsController::class, 'deleteAvatar'])->name('avatar.delete');
        Route::put('password', [UserSettingsController::class, 'changePassword'])->name('password');
    });

    // Company Settings
    Route::prefix('company/settings')->name('company.settings.')->group(function () {
        Route::get('/', [CompanySettingsController::class, 'show'])
            ->middleware('permission:view-company-settings')
            ->name('show');
        Route::put('/', [CompanySettingsController::class, 'update'])
            ->middleware('permission:update-company-settings')
            ->name('update');
        Route::post('ai-key/reveal', [CompanySettingsController::class, 'revealAiApiKey'])
            ->middleware('permission:update-company-settings')
            ->name('ai-key.reveal');
        Route::post('logo', [CompanySettingsController::class, 'uploadLogo'])
            ->middleware('permission:update-company-settings')
            ->name('logo.upload');
        Route::delete('logo', [CompanySettingsController::class, 'deleteLogo'])
            ->middleware('permission:update-company-settings')
            ->name('logo.delete');
    });
});
