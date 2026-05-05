<?php

use App\Http\Controllers\Api\RequestReasonTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::post('requestType/{requestTypeId}/reasons', [RequestReasonTypeController::class, 'syncReasons']);
});
