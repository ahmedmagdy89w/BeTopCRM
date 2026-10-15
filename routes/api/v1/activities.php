<?php

use App\Http\Controllers\Api\CustomerActivityController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimelineController;
use Illuminate\Support\Facades\Route;

// Customer Activities (nested under customers + global list), Tasks, Notes, Timeline
Route::middleware('auth:sanctum')->group(function () {
    // Customer Activities
    Route::get('activities', [CustomerActivityController::class, 'globalIndex'])->name('activities.global');
    Route::apiResource('customers.activities', CustomerActivityController::class)->except(['show']);
    Route::patch('customers/{customer}/activities/{activity}/complete', [CustomerActivityController::class, 'complete']);

    // Tasks
    Route::get('tasks/my-summary', [TaskController::class, 'mySummary'])->name('tasks.my-summary');
    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

    // Notes (Phase 27)
    Route::get('{entityType}/{entityId}/notes', [NoteController::class, 'index'])
        ->where('entityType', 'customers|opportunities')
        ->name('notes.index');
    Route::post('{entityType}/{entityId}/notes', [NoteController::class, 'store'])
        ->where('entityType', 'customers|opportunities')
        ->name('notes.store');
    Route::put('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('notes/{note}/toggle-pin', [NoteController::class, 'togglePin'])->name('notes.toggle-pin');

    // Activity Timeline (Phase 28)
    Route::get('{entityType}/{entityId}/timeline', [TimelineController::class, 'index'])
        ->where('entityType', 'customers|opportunities')
        ->name('timeline.index');
});
