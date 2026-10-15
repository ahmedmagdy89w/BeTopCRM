<?php

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\PublicDocumentController;
use Illuminate\Support\Facades\Route;

// Public Signed Document Downloads (signed URLs for WhatsApp/Email customers)
Route::get('public/quotations/{quotation}/download', [PublicDocumentController::class, 'downloadQuotation'])
    ->name('public.quotations.download');
Route::get('public/invoices/{invoice}/download', [PublicDocumentController::class, 'downloadInvoice'])
    ->name('public.invoices.download');

// Import + Export (protected)
Route::middleware('auth:sanctum')->group(function () {
    // Import
    Route::prefix('import')->name('import.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index')->middleware('permission:import-data');
        Route::post('{entityType}', [ImportController::class, 'store'])->name('store')->middleware('permission:import-data');
        Route::get('{entityType}/template', [ImportController::class, 'template'])->name('template')->middleware('permission:import-data');
        Route::get('{import}', [ImportController::class, 'show'])->name('show');
    });

    // Export (permission:export-data)
    Route::prefix('export')->middleware('permission:export-data')->name('export.')->group(function () {
        Route::get('leads', [ExportController::class, 'leads'])->name('leads');
        Route::get('customers', [ExportController::class, 'customers'])->name('customers');
        Route::get('opportunities', [ExportController::class, 'opportunities'])->name('opportunities');
        Route::get('products', [ExportController::class, 'products'])->name('products');
        Route::get('categories', [ExportController::class, 'categories'])->name('categories');
    });
});
