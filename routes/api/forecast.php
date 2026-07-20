<?php

use App\Http\Controllers\Api\ForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('forecast/clients', [ForecastController::class, 'clients']);
    Route::put('forecast/clients/{idCliente}/emails', [ForecastController::class, 'updateClientEmails'])->whereNumber('idCliente');
    Route::put('forecast/clients/{idCliente}/ext', [ForecastController::class, 'updateClientExt'])->whereNumber('idCliente');
    Route::get('forecast/sales-engineer/{salesEngineerId}/{year}', [ForecastController::class, 'indexBySalesEngineer'])->whereNumber(['salesEngineerId', 'year']);
    Route::get('forecast/{idClient}/{year}/{month}/invoices', [ForecastController::class, 'invoicesByMonth'])->whereNumber(['idClient', 'year', 'month']);
    Route::get('forecast/{idClient}/{year}/{month}/invoices/products', [ForecastController::class, 'invoiceProductsByMonth'])->whereNumber(['idClient', 'year', 'month']);
    Route::get('forecast/{idClient}/{year}/{month}/invoices/export', [ForecastController::class, 'exportInvoicesByMonth'])->whereNumber(['idClient', 'year', 'month']);
    Route::get('forecast/{idClient}/{year}', [ForecastController::class, 'index'])->whereNumber(['idClient', 'year']);
    Route::post('forecast', [ForecastController::class, 'store']);
});
