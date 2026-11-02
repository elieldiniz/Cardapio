<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic cleanup of unchosen, old replaced and never-reviewed dish videos.
Schedule::command('videos:prune')->dailyAt('04:00')->onOneServer();

// Address and notes of orders that ended 30+ days ago are cleared, as the checkout privacy notice says (RNF-08).
Schedule::command('orders:prune-personal-data')->dailyAt('04:30')->onOneServer();

// Customers with no order for two years lose their name and WhatsApp (RNF-08).
Schedule::command('customers:prune-inactive')->dailyAt('05:00')->onOneServer();
