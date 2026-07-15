<?php

use App\Console\Commands\ReleaseStaleRequestNumberReservations;
use App\Console\Commands\SendPendingApprovalReminders;
use App\Console\Commands\SyncForecastSales;
use App\Console\Commands\SyncProductCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendPendingApprovalReminders::class)
    ->dailyAt('15:56')
    ->timezone('America/Mexico_City');

Schedule::command(SyncForecastSales::class)
    ->dailyAt('02:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-forecast.log'));

Schedule::command(ReleaseStaleRequestNumberReservations::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command(SyncProductCatalog::class)
    ->dailyAt('02:30')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-product-catalog.log'));
