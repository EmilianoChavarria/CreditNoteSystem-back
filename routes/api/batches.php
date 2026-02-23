<?php

use App\Http\Controllers\Api\BatchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('batches', [BatchController::class, 'store']);
    Route::get('batches/{id}', [BatchController::class, 'show']);
});
