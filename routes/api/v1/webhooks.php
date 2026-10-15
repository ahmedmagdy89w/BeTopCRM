<?php

use App\Http\Controllers\Api\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

// Webhooks (Phase 25)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('webhooks', WebhookEndpointController::class);
    Route::post('webhooks/{webhook}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhooks.rotate-secret');
    Route::get('webhooks/{webhook}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhooks.deliveries');
    Route::post('webhook-deliveries/{delivery}/redeliver', [WebhookEndpointController::class, 'redeliver'])->name('webhook-deliveries.redeliver');
});
