<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('users', [UserController::class, 'getAll']);
    Route::get('usersPag', [UserController::class, 'index']);
    Route::get('users/managers', [UserController::class, 'usersBySalesAndManagerRoles']);
    Route::get('users/me', [UserController::class, 'me']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);
});
