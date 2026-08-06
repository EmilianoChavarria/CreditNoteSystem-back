<?php

use App\Http\Controllers\Api\NationalCustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('national-customers', [NationalCustomerController::class, 'index']);
    Route::get('national-customers/search', [NationalCustomerController::class, 'search']);
    Route::post('national-customers', [NationalCustomerController::class, 'store']);
    Route::post('national-customers/bulk', [NationalCustomerController::class, 'bulkStore']);
    Route::put('national-customers/{customerNumber}', [NationalCustomerController::class, 'update']);
    Route::delete('national-customers/{customerNumber}', [NationalCustomerController::class, 'destroy']);
});
