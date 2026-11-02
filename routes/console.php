<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('interviews:prune-recordings')->dailyAt('02:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('vokasim:send-reminders')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
