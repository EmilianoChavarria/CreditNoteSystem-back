<?php

use App\Http\Controllers\Api\UserAssignmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('users', [UserController::class, 'getAll']);
    Route::get('usersPag', [UserController::class, 'index']);
    Route::get('users/assignment/leaders', [UserAssignmentController::class, 'leaders']);
    Route::get('users/assignment/assignable-users', [UserAssignmentController::class, 'assignableUsers']);
    Route::get('users/managers', [UserController::class, 'usersBySalesAndManagerRoles']);
    Route::get('users/me', [UserController::class, 'me']);
    Route::patch('users/me/password', [UserController::class, 'changePassword']);
    Route::patch('users/{id}/password', [UserController::class, 'changePasswordByUserId']);
    Route::get('users/{leaderUserId}/assignments', [UserAssignmentController::class, 'index']);
    Route::put('users/assignments', [UserAssignmentController::class, 'upsert']);
    Route::delete('users/{leaderUserId}/assignments/{assignedUserId}', [UserAssignmentController::class, 'destroy']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);
});
