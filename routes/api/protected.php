<?php

use App\Http\Controllers\Api\ProtectedController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt')->group(function () {
    Route::get('me', [ProtectedController::class, 'me']);
    Route::get('admin/only', [ProtectedController::class, 'adminOnly'])->middleware('role:admin');
});
