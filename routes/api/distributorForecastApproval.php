<?php

use App\Http\Controllers\Api\DistributorForecastApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('distributors/forecast/change-requests', [DistributorForecastApprovalController::class, 'submit']);
    Route::get('distributors/forecast/change-requests/pending', [DistributorForecastApprovalController::class, 'pendingForApprover']);
    Route::get('distributors/forecast/change-requests/mine', [DistributorForecastApprovalController::class, 'myRequests']);
    Route::get('distributors/forecast/change-requests/history', [DistributorForecastApprovalController::class, 'monthHistory']);
    Route::post('distributors/forecast/change-requests/{id}/approve', [DistributorForecastApprovalController::class, 'approve']);
    Route::post('distributors/forecast/change-requests/{id}/reject', [DistributorForecastApprovalController::class, 'reject']);
});
