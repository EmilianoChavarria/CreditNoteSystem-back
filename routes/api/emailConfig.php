<?php

use App\Http\Controllers\Api\EmailConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('email-config', [EmailConfigController::class, 'get']);
    Route::put('email-config', [EmailConfigController::class, 'upsert']);
});
