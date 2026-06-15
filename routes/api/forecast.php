<?php

use App\Http\Controllers\Api\ForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('forecast/sales-engineer/{salesEngineerId}/{year}', [ForecastController::class, 'indexBySalesEngineer'])->whereNumber(['salesEngineerId', 'year']);
    Route::get('forecast/{idClient}/{year}', [ForecastController::class, 'index'])->whereNumber(['idClient', 'year']);
    Route::post('forecast', [ForecastController::class, 'store']);
});
