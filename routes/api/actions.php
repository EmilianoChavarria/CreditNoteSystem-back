<?php

use App\Http\Controllers\Api\ActionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('actions', [ActionController::class, 'store']);
});
