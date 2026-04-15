<?php

use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'getAll']);
    Route::get('invoices/{clientId}', [InvoiceController::class, 'getInvoicesByClientId']);
});
