<?php

use App\Http\Controllers\Api\RequestTypeController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt'])->group(function () {
    Route::get('requestType', [RequestTypeController::class, 'getAll']);
    Route::post('requestType', [RequestTypeController::class, 'saveRequestType']);
    Route::put('requestType/{id}', [RequestTypeController::class, 'updateRequestType']);
    Route::delete('requestType/{id}', [RequestTypeController::class, 'deleteRequestType']);
});
