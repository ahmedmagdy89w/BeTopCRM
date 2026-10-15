<?php

use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaxController;
use Illuminate\Support\Facades\Route;

// Sales catalog: products, categories, taxes, tags
Route::middleware('auth:sanctum')->group(function () {
    // Tags
    Route::apiResource('tags', TagController::class)->except(['show']);

    // Product Categories
    Route::get('product-categories/tree', [ProductCategoryController::class, 'tree'])->name('product-categories.tree');
    Route::post('product-categories/reorder', [ProductCategoryController::class, 'reorder'])->name('product-categories.reorder');
    Route::get('product-categories/{productCategory}/products', [ProductController::class, 'byCategory'])->name('product-categories.products');
    Route::apiResource('product-categories', ProductCategoryController::class);

    // Products
    Route::patch('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
    Route::get('products/{product}/opportunities', [ProductController::class, 'opportunities'])->name('products.opportunities');
    Route::apiResource('products', ProductController::class);

    // Taxes
    Route::apiResource('taxes', TaxController::class);
});
