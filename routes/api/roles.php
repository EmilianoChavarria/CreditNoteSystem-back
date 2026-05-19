<?php

use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt'])->group(function () {
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{id}', [RoleController::class, 'show']);
    Route::put('roles/{id}', [RoleController::class, 'update']);
    Route::patch('roles/{id}', [RoleController::class, 'update']);
    Route::patch('roles/{id}/equivalent-role', [RoleController::class, 'setEquivalentRole']);
    Route::delete('roles/{id}', [RoleController::class, 'destroy']);
});
