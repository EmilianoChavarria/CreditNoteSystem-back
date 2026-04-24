<?php

use App\Http\Controllers\Api\ChargeTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('charge-types', [ChargeTypeController::class, 'index']);
});
