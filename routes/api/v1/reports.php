<?php

use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AsyncJobController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

// Dashboard, search, reports, async jobs, AI chat
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('dashboard/lead-stats', [DashboardController::class, 'leadStats']);
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('dashboard/activity-feed', [DashboardController::class, 'activityFeed'])->name('dashboard.activity-feed');

    // Global Search
    Route::get('search', [SearchController::class, 'index'])->name('search.index');

    // Reports (permission:view-reports)
    Route::prefix('reports')->middleware('permission:view-reports')->name('reports.')->group(function () {
        Route::get('user-performance', [ReportController::class, 'userPerformance'])->name('user-performance');
        Route::get('company', [ReportController::class, 'company'])->name('company');
        Route::get('customer', [ReportController::class, 'customer'])->name('customer');
        Route::get('lead-pipeline', [ReportController::class, 'leadPipeline'])->name('lead-pipeline');
        Route::get('product-performance', [ReportController::class, 'productPerformance'])->name('product-performance');
        Route::get('user-product-performance', [ReportController::class, 'userProductPerformance'])->name('user-product-performance');
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('lead-pipeline/export', [ReportController::class, 'exportLeadPipeline'])->name('lead-pipeline.export');
        Route::get('product-performance/export', [ReportController::class, 'exportProductPerformance'])->name('product-performance.export');
        Route::get('user-product-performance/export', [ReportController::class, 'exportUserProductPerformance'])->name('user-product-performance.export');
        Route::get('sales/export', [ReportController::class, 'exportSales'])->name('sales.export');
        Route::get('user-performance/export', [ReportController::class, 'exportUserPerformance'])->name('user-performance.export');
        Route::get('company/export', [ReportController::class, 'exportCompany'])->name('company.export');
        Route::get('customer/export', [ReportController::class, 'exportCustomer'])->name('customer.export');

        // Async (queued) report generation
        Route::post('user-performance/async', [ReportController::class, 'userPerformanceAsync'])->name('user-performance.async');
        Route::post('company/async', [ReportController::class, 'companyAsync'])->name('company.async');
        Route::post('customer/async', [ReportController::class, 'customerAsync'])->name('customer.async');
        Route::post('lead-pipeline/async', [ReportController::class, 'leadPipelineAsync'])->name('lead-pipeline.async');
        Route::post('product-performance/async', [ReportController::class, 'productPerformanceAsync'])->name('product-performance.async');
        Route::post('user-product-performance/async', [ReportController::class, 'userProductPerformanceAsync'])->name('user-product-performance.async');
        Route::post('sales/async', [ReportController::class, 'salesAsync'])->name('sales.async');
        Route::post('lead-pipeline/export/async', [ReportController::class, 'exportLeadPipelineAsync'])->name('lead-pipeline.export.async');
        Route::post('product-performance/export/async', [ReportController::class, 'exportProductPerformanceAsync'])->name('product-performance.export.async');
        Route::post('user-product-performance/export/async', [ReportController::class, 'exportUserProductPerformanceAsync'])->name('user-product-performance.export.async');
        Route::post('sales/export/async', [ReportController::class, 'exportSalesAsync'])->name('sales.export.async');

        // Async (queued) report export generation
        Route::post('user-performance/export/async', [ReportController::class, 'exportUserPerformanceAsync'])->name('user-performance.export.async');
        Route::post('company/export/async', [ReportController::class, 'exportCompanyAsync'])->name('company.export.async');
        Route::post('customer/export/async', [ReportController::class, 'exportCustomerAsync'])->name('customer.export.async');
    });

    // Async Jobs (generic polling)
    Route::prefix('async-jobs')->name('async-jobs.')->group(function () {
        Route::get('/', [AsyncJobController::class, 'index'])->name('index');
        Route::get('{asyncJob}', [AsyncJobController::class, 'show'])->name('show');
        Route::get('{asyncJob}/download', [AsyncJobController::class, 'download'])->name('download');
    });

    // AI Reporting & Analytics Chat (stateless, async)
    Route::prefix('ai-chat')->middleware('permission:create-ai-chats')->name('ai-chat.')->group(function () {
        Route::get('suggested-questions', [AiChatController::class, 'suggestedQuestions'])->name('suggested-questions');
        Route::post('async', [AiChatController::class, 'chatAsync'])
            ->middleware('throttle:10,1')
            ->name('chat.async');
    });
});
