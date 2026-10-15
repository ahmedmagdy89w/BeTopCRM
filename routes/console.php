<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('crm:send-follow-up-reminders')->daily()->at('08:00');
Schedule::command('crm:send-activity-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:send-opportunity-expiry-alerts')->daily()->at('08:00');
Schedule::command('crm:send-task-reminders')->daily()->at('08:00');
Schedule::command('crm:send-scheduled-campaigns')->everyMinute()->withoutOverlapping();
Schedule::command('crm:prune-audit-logs')->weekly();
Schedule::command('crm:recalculate-lead-scores')->daily();
