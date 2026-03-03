<?php

use App\Http\Controllers\Api\ModuleController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt'])->group(function () {
    Route::get('modules', [ModuleController::class, 'index']);
    Route::post('modules', [ModuleController::class, 'store']);
    Route::get('modules/{id}', [ModuleController::class, 'show']);
    Route::put('modules/{id}', [ModuleController::class, 'update']);
    Route::delete('modules/{id}', [ModuleController::class, 'destroy']);
});
