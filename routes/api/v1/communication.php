<?php

use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

// Notifications, email templates, campaigns, WhatsApp
Route::middleware('auth:sanctum')->group(function () {
    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::patch('{id}/read', [NotificationController::class, 'markRead'])->name('mark-read');
        Route::patch('read-all', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    // Email Templates
    Route::post('email-templates/generate', [EmailTemplateController::class, 'generate'])->name('email-templates.generate');
    Route::apiResource('email-templates', EmailTemplateController::class)->parameters(['email-templates' => 'template']);
    Route::post('email-templates/{template}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');

    // Campaigns
    Route::apiResource('campaigns', CampaignController::class);
    Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
    Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->name('campaigns.schedule');
    Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::get('campaigns/{campaign}/recipients', [CampaignController::class, 'recipients'])->name('campaigns.recipients');

    // Global WhatsApp Service
    Route::post('customers/{customer}/whatsapp/prepare', [WhatsAppController::class, 'prepareCustomer'])->name('whatsapp.customer.prepare');
    Route::post('customers/{customer}/whatsapp/send', [WhatsAppController::class, 'sendCustomer'])->name('whatsapp.customer.send');
    Route::post('customers/{customer}/opportunities/{opportunity}/whatsapp/send', [WhatsAppController::class, 'sendOpportunity'])->name('whatsapp.opportunity.send');
    Route::post('customers/{customer}/opportunities/{opportunity}/quotations/{quotation}/whatsapp/prepare', [WhatsAppController::class, 'prepareQuotation'])->name('whatsapp.quotation.prepare');
    Route::post('customers/{customer}/opportunities/{opportunity}/invoice/{invoice}/whatsapp/prepare', [WhatsAppController::class, 'prepareInvoice'])->name('whatsapp.invoice.prepare');
});
