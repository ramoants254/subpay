<?php

use App\Jobs\ProcessDueSubscriptions;
use App\Jobs\ReconcilePendingCharges;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ProcessDueSubscriptions)->everyMinute();

// Scan for stuck transactions every 5 minutes
Schedule::job(new ReconcilePendingCharges)->everyFiveMinutes();
