<?php

use App\Http\Controllers\Api\ReturnOrderController;
use App\Http\Controllers\Api\ReturnOrderRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    // Productos de una factura con cantidades disponibles a devolver
    Route::get('invoices/{folio}/products/{clientId}', [ReturnOrderController::class, 'getInvoiceProducts']);

    // Historial de devoluciones de un producto específico
    Route::get('invoices/{folio}/products/{clientId}/{conceptoIndex}/history', [ReturnOrderController::class, 'getProductReturnHistory']);

    // Órdenes de devolución
    Route::get('return-orders/search', [ReturnOrderController::class, 'search']);
    Route::get('return-orders/client/{clientId}', [ReturnOrderController::class, 'getByClientId']);
    Route::get('return-orders/{id}', [ReturnOrderController::class, 'show']);
    Route::post('return-orders', [ReturnOrderController::class, 'store']);
    Route::patch('return-orders/{id}/charge', [ReturnOrderController::class, 'updateCharge']);
    Route::post('return-orders/{id}/items', [ReturnOrderController::class, 'addItems']);
    Route::patch('return-orders/{id}/items/{itemId}', [ReturnOrderController::class, 'updateItemQuantity']);
    Route::delete('return-orders/{id}/items/{itemId}', [ReturnOrderController::class, 'removeItem']);

    // Vínculo orden de devolución ↔ request (material return)
    Route::post('return-order-requests', [ReturnOrderRequestController::class, 'store']);
    Route::get('return-order-requests/by-request/{requestId}', [ReturnOrderRequestController::class, 'showByRequestId']);
    Route::get('return-order-requests/{id}', [ReturnOrderRequestController::class, 'show']);
    Route::put('return-order-requests/{id}/items', [ReturnOrderRequestController::class, 'updateItems']);
    Route::put('return-order-requests/{id}/items/{itemId}', [ReturnOrderRequestController::class, 'updateItem']);
});
