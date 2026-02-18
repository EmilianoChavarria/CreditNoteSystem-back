<?php

use App\Http\Controllers\Api\RequestController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt'])->group(function () {
    Route::get('requests', [RequestController::class, 'getAll']);
    Route::get('requests/reasons', [RequestController::class, 'getAllReasons']);
    Route::post('requests/newRequest', [RequestController::class, 'createRequest']);
});
