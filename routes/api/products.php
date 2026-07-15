<?php

use App\Http\Controllers\Api\ProductCatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('products/catalog', [ProductCatalogController::class, 'getAll']);
    Route::post('products/catalog/classify', [ProductCatalogController::class, 'classify']);
});
