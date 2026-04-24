<?php

use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'getAll']);
    Route::get('invoices/{clientId}/search', [InvoiceController::class, 'search']);
    Route::get('invoices/{clientId}', [InvoiceController::class, 'getInvoicesByClientId']);
    Route::get('invoices/{clientId}/charge-type/{chargeType}', [InvoiceController::class, 'getInvoicesByClientIdAndChargeType']);
});
