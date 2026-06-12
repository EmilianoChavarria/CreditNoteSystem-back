<?php

use App\Http\Controllers\Api\ForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('forecast/{idClient}/{year}', [ForecastController::class, 'index']);
    Route::post('forecast', [ForecastController::class, 'store']);
});
