<?php

use App\Http\Controllers\Api\BatchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('batches', [BatchController::class, 'index']);
    Route::post('batches', [BatchController::class, 'store']);
    Route::get('batches/{id}', [BatchController::class, 'show']);
    Route::get('batches/{id}/requests', [BatchController::class, 'requests']);
});
