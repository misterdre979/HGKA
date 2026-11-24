<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the standard Laravel scheduler cron entry: * * * * * php artisan schedule:run
Schedule::command('hgka:backup')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('hgka:fee-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('hgka:library-overdue')->dailyAt('08:30')->withoutOverlapping();
Schedule::command('hgka:exam-reminders')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
