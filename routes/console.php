<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Background Tasks (NexSphere Master Hub)
|--------------------------------------------------------------------------
*/

// Fase 2: Automated Subscription & Invoicing Lifecycle
Schedule::command('tenants:check-subscriptions')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/subscription-cron.log'));

// Fase 3: Node Health & Dead-Node Alerting
Schedule::command('tenants:check-node-health')
    ->everyTenMinutes()
    ->withoutOverlapping();
