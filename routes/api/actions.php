<?php

use App\Http\Controllers\Api\ActionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('actions', [ActionController::class, 'getAll']);
    Route::post('actions', [ActionController::class, 'store']);
});
