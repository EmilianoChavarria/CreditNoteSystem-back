<?php

use App\Http\Controllers\Api\ChargePolicyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('charge-policies', [ChargePolicyController::class, 'index']);
    Route::post('charge-policies/sync', [ChargePolicyController::class, 'sync']);
    Route::get('charge-policies/{id}', [ChargePolicyController::class, 'show']);
    Route::post('charge-policies', [ChargePolicyController::class, 'store']);
    Route::put('charge-policies/{id}', [ChargePolicyController::class, 'update']);
    Route::delete('charge-policies/{id}', [ChargePolicyController::class, 'destroy']);
});
