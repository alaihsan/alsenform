<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Exams whose time ran out while the page was closed or offline are handed in by the server.
Schedule::command('exam:submit-expired')->everyMinute()->withoutOverlapping();
