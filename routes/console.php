<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic cleanup of unchosen, old replaced and never-reviewed dish videos.
Schedule::command('videos:prune')->dailyAt('04:00')->onOneServer();
