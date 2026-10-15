<?php

use App\Http\Controllers\Api\CustomFieldDefinitionController;
use App\Http\Controllers\Api\CustomFieldGroupController;
use Illuminate\Support\Facades\Route;

// Custom Fields
Route::middleware('auth:sanctum')->prefix('custom-fields')->name('custom-fields.')->group(function () {
    $entityTypes = array_keys(config('crm.custom_fields.entities', []));

    Route::get('entities', [CustomFieldGroupController::class, 'entities'])->name('entities');
    Route::post('groups', [CustomFieldGroupController::class, 'store'])->name('groups.store');
    Route::put('groups/{group}', [CustomFieldGroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{group}', [CustomFieldGroupController::class, 'destroy'])->name('groups.destroy');

    Route::get('definitions/{definition}', [CustomFieldDefinitionController::class, 'show'])->name('show');
    Route::put('definitions/{definition}', [CustomFieldDefinitionController::class, 'update'])->name('update');
    Route::delete('definitions/{definition}', [CustomFieldDefinitionController::class, 'destroy'])->name('destroy');

    Route::get('{entityType}/categories/{category}', [CustomFieldGroupController::class, 'showCategory'])
        ->whereIn('entityType', $entityTypes)
        ->name('categories.show');
    Route::post('{entityType}/categories/{category}/definitions', [CustomFieldDefinitionController::class, 'storeCategory'])
        ->whereIn('entityType', $entityTypes)
        ->name('categories.definitions.store');
    Route::get('{entityType}', [CustomFieldGroupController::class, 'showEntity'])
        ->whereIn('entityType', $entityTypes)
        ->name('index');
    Route::post('{entityType}', [CustomFieldDefinitionController::class, 'store'])
        ->whereIn('entityType', $entityTypes)
        ->name('store');
});
