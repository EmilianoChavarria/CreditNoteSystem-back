<?php


use App\Http\Controllers\Api\ModulePermissionController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt'])->group(function () {
    Route::post('rolesPermission/assign', [ModulePermissionController::class, 'assignPermission']);
    Route::get('rolesPermission', [ModulePermissionController::class, 'getAll']);
});
