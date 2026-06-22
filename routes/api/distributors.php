<?php

use App\Http\Controllers\Api\DistributorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::put('distributors/{id}', [DistributorController::class, 'update']);
});
