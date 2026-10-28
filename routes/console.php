<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('demo:prune-stale')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('workspaces:purge-expired')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('invoices:purge-expired')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('uploads:purge-temporary --execute')->hourly()->withoutOverlapping();
Schedule::command('uploads:purge-orphans --execute')->daily()->withoutOverlapping();
Schedule::command('ai:purge-content --execute')->daily()->withoutOverlapping();
Schedule::command('logs:prune --execute')->hourly()->withoutOverlapping();
Schedule::command('queue:prune-failed')->daily();
