<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Exams whose time ran out while the page was closed or offline are handed in by the server.
Schedule::command('exam:submit-expired')->everyMinute()->withoutOverlapping();

// At night, for servers run by the scheduler instead of lan:serve (which does this by itself):
// a copy of the database, then records past their retention removed.
Schedule::command('db:backup --if-older-than=20')->timezone(config('app.display_timezone'))->dailyAt('01:00')->withoutOverlapping();
Schedule::command('model:prune')->timezone(config('app.display_timezone'))->dailyAt('01:30');
