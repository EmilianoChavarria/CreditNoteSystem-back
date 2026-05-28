<?php

use App\Http\Controllers\Api\ClassificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('classifications', [ClassificationController::class, 'store']);
    Route::get('classifications/grouped', [ClassificationController::class, 'getClassificationGrouped']);
    Route::get('classifications/requestType/{typeRequestId}', [ClassificationController::class, 'getAllByRequestTypeId']);
    Route::get('classifications/my-pending', [ClassificationController::class, 'getUsedInMyPending']);
    Route::post('classifications/{id}/reasons', [ClassificationController::class, 'syncReasons']);
});
