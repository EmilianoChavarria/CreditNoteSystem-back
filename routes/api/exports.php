<?php

use App\Http\Controllers\Api\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('exports/excel', [ExportController::class, 'excel']);
});
