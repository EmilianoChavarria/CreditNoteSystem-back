<?php

use App\Http\Controllers\Api\ForecastApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('forecast/change-requests', [ForecastApprovalController::class, 'submit']);
    Route::get('forecast/change-requests/pending', [ForecastApprovalController::class, 'pendingForApprover']);
    Route::get('forecast/change-requests/mine', [ForecastApprovalController::class, 'myRequests']);
    Route::get('forecast/change-requests/history', [ForecastApprovalController::class, 'monthHistory']);
    Route::post('forecast/change-requests/{id}/approve', [ForecastApprovalController::class, 'approve']);
    Route::post('forecast/change-requests/{id}/reject', [ForecastApprovalController::class, 'reject']);
});
