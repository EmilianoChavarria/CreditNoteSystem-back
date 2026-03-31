<?php

use App\Http\Controllers\Api\AdminSecurityController;
use App\Http\Controllers\Api\PasswordRequirementsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('security/users/{id}/unlock', [AdminSecurityController::class, 'unlockUser']);
    Route::post('security/ips/unlock', [AdminSecurityController::class, 'unlockIp']);
});

// Rutas de requisitos de contraseña
Route::post('password-requirements/validate', [PasswordRequirementsController::class, 'validatePassword']);
Route::middleware('jwt')->group(function () {
    Route::get('password-requirements', [PasswordRequirementsController::class, 'getRequirements']);
    Route::get('password-requirements/formatted', [PasswordRequirementsController::class, 'getRequirementsFormatted']);
});

Route::middleware(['jwt'])->group(function () {
    Route::put('password-requirements', [PasswordRequirementsController::class, 'updateRequirements']);
});
