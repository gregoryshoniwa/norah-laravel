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

// Finalize EcoCash payments whose outcome was never collected by a poll or
// the provider notify callback (direct-API clients that stopped polling,
// lost callbacks). Same schedule:run cron entry as above.
Schedule::command('ecocash:reconcile-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
