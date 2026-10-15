<?php

use App\Http\Controllers\Api\CustomerController;
use Illuminate\Support\Facades\Route;

// Customers (unified: type individual|company, is_lead flag for leads vs customers)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('customers', CustomerController::class);
    Route::patch('customers/{customer}/lead-status', [CustomerController::class, 'updateLeadStatus'])->name('customers.lead-status');
    Route::patch('customers/{customer}/assign', [CustomerController::class, 'assign'])->name('customers.assign');
    Route::post('customers/{customer}/convert', [CustomerController::class, 'convert'])->name('customers.convert');
    Route::post('customers/{customer}/tags', [CustomerController::class, 'syncTags'])->name('customers.tags.sync');
    Route::get('customers/{customer}/score', [CustomerController::class, 'score'])->name('customers.score');
    Route::post('customers/{customer}/score/recalculate', [CustomerController::class, 'recalculateScore'])->name('customers.score.recalculate');
});
