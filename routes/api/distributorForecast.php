<?php

use App\Http\Controllers\Api\DistributorForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('distributors/{distributorId}/forecast/{year}', [DistributorForecastController::class, 'index']);
    Route::post('distributors/{distributorId}/forecast', [DistributorForecastController::class, 'store']);
    Route::put('distributors/{distributorId}/forecast/{year}/{month}', [DistributorForecastController::class, 'updateMonth']);
});
