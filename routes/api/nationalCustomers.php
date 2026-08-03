<?php

use App\Http\Controllers\Api\NationalCustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('national-customers', [NationalCustomerController::class, 'index']);
    Route::put('national-customers/{customerNumber}', [NationalCustomerController::class, 'update']);
});
