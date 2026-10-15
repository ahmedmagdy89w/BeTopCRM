<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Roles, users, audit logs
Route::middleware('auth:sanctum')->group(function () {
    // Audit Logs
    Route::prefix('audit-logs')->middleware('permission:view-audit-log')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('entity/{type}/{id}', [AuditLogController::class, 'forEntity'])->name('for-entity');
    });

    // Role Management (admin only)
    Route::middleware('permission:view-users')->group(function () {
        Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions.index');
        Route::apiResource('roles', RoleController::class);
    });

    // User Management (admin/manager)
    // Registered before the resource to prevent `assignable` binding as `{user}`.
    Route::get('users/assignable', [UserController::class, 'assignable'])->name('users.assignable');
    Route::get('users/assignable/{user}', [UserController::class, 'showAssignable'])->name('users.assignable.show');
    Route::middleware('permission:view-users')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::put('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    });
});
