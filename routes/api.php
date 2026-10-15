<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('routes/api/v1/auth.php');
    require base_path('routes/api/v1/customers.php');
    require base_path('routes/api/v1/opportunities.php');
    require base_path('routes/api/v1/activities.php');
    require base_path('routes/api/v1/catalog.php');
    require base_path('routes/api/v1/communication.php');
    require base_path('routes/api/v1/reports.php');
    require base_path('routes/api/v1/administration.php');
    require base_path('routes/api/v1/settings.php');
    require base_path('routes/api/v1/custom-fields.php');
    require base_path('routes/api/v1/attachments.php');
    require base_path('routes/api/v1/data-transfer.php');
    require base_path('routes/api/v1/webhooks.php');
});
