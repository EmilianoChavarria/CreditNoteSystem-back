<?php

use App\Http\Controllers\Api\AdminSecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt', 'role:admin'])->group(function () {
    Route::post('security/users/{id}/unlock', [AdminSecurityController::class, 'unlockUser']);
    Route::post('security/ips/unlock', [AdminSecurityController::class, 'unlockIp']);
});
