<?php

use App\Http\Controllers\Api\AttachmentController;
use Illuminate\Support\Facades\Route;

// Attachments — entity-scoped upload/list + direct access
Route::middleware('auth:sanctum')->group(function () {
    // Attachments — entity-scoped upload/list
    Route::get('{entityType}/{entityId}/attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::post('{entityType}/{entityId}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');

    // Attachment direct access
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
});
