<?php

use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('workflows', [WorkflowController::class, 'getAll']);
    Route::get('workflows/{id}', [WorkflowController::class, 'getById']);
    Route::post('workflows', [WorkflowController::class, 'store']);
    Route::put('workflows/{id}', [WorkflowController::class, 'update']);
    Route::delete('workflows/{id}', [WorkflowController::class, 'delete']);
});
