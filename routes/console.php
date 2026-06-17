<?php

use App\Console\Commands\SendPendingApprovalReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendPendingApprovalReminders::class)
    ->dailyAt('15:56')
    ->timezone('America/Mexico_City');


