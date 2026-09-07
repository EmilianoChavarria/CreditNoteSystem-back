<?php

use App\Console\Commands\NotifyClientsApprovedForecast;
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

// Schedule::command(SendPendingApprovalReminders::class)
//     ->dailyAt('15:56')
//     ->timezone('America/Mexico_City');

Schedule::command(SyncForecastSales::class)
    ->dailyAt('02:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/sync-forecast.log'));

// Schedule::command(ReleaseStaleRequestNumberReservations::class)
//     ->everyFifteenMinutes()
//     ->withoutOverlapping();

// Aviso al cliente de sus cambios de objetivo ya aprobados: un solo correo por
// cliente con todos los meses que le falten por notificar.
Schedule::command(NotifyClientsApprovedForecast::class)
    ->dailyAt('21:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/notify-approved-forecast.log'));

Schedule::command(SyncProductCatalog::class)
    ->dailyAt('02:00')
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync-product-catalog.log'));
