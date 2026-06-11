<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-payouts cron. Requires the `php artisan schedule:run` cron entry
// to be installed on the server (every minute).
Schedule::command('payouts:run-scheduled')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
