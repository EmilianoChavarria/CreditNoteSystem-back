<?php

use App\Http\Controllers\Api\RequestTypePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('requestTypePermissions/assign', [RequestTypePermissionController::class, 'assignPermission']);
    Route::get('requestTypePermissions', [RequestTypePermissionController::class, 'getAll']);
    Route::get('requestTypePermissions/role/{roleId}', [RequestTypePermissionController::class, 'getByRole']);
    Route::get('requestTypePermissions/request-type/{requestTypeId}', [RequestTypePermissionController::class, 'getByRequestType']);
    Route::post('requestTypePermissions/check', [RequestTypePermissionController::class, 'check']);
});
