<?php

use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OpportunityActivityController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\OpportunityProductController;
use App\Http\Controllers\Api\QuotationController;
use Illuminate\Support\Facades\Route;

// Opportunities (nested under customers + global board/table)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('opportunities', [OpportunityController::class, 'globalIndex'])->name('opportunities.global');
    Route::get('quotations', [QuotationController::class, 'globalIndex'])->name('quotations.global');
    Route::get('invoices', [InvoiceController::class, 'globalIndex'])->name('invoices.global');
    Route::apiResource('customers.opportunities', OpportunityController::class);
    Route::get('customers/{customer}/opportunities/{opportunity}/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::post('customers/{customer}/opportunities/{opportunity}/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::post('customers/{customer}/opportunities/{opportunity}/invoice', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('customers/{customer}/opportunities/{opportunity}/invoice', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::get('customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/preview', [QuotationController::class, 'preview'])->name('quotations.preview');
    Route::post('customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::get('customers/{customer}/opportunities/{opportunity}/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::patch('customers/{customer}/opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->name('customers.opportunities.stage');

    // Opportunity Activities (nested under opportunities)
    Route::apiResource('customers.opportunities.activities', OpportunityActivityController::class)->except(['show'])->parameters([
        'activities' => 'activity',
    ]);
    Route::patch('customers/{customer}/opportunities/{opportunity}/activities/{activity}/complete', [OpportunityActivityController::class, 'complete'])->name('customers.opportunities.activities.complete');

    // Opportunity Products (nested under opportunities)
    Route::get('customers/{customer}/opportunities/{opportunity}/products', [OpportunityProductController::class, 'index'])->name('customers.opportunities.products.index');
    Route::post('customers/{customer}/opportunities/{opportunity}/products', [OpportunityProductController::class, 'store'])->name('customers.opportunities.products.store');
    Route::put('customers/{customer}/opportunities/{opportunity}/products/{product}', [OpportunityProductController::class, 'update'])->name('customers.opportunities.products.update');
    Route::delete('customers/{customer}/opportunities/{opportunity}/products/{product}', [OpportunityProductController::class, 'destroy'])->name('customers.opportunities.products.destroy');
    Route::post('customers/{customer}/opportunities/{opportunity}/products/sync', [OpportunityProductController::class, 'sync'])->name('customers.opportunities.products.sync');
});
