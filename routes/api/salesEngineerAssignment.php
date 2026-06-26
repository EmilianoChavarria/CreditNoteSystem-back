<?php

use App\Http\Controllers\Api\SalesEngineerAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('sales-engineer-assignment/managers', [SalesEngineerAssignmentController::class, 'managers']);
    Route::get('sales-engineer-assignment/my-engineers', [SalesEngineerAssignmentController::class, 'myEngineers']);
    Route::get('sales-engineer-assignment/all-engineers', [SalesEngineerAssignmentController::class, 'allEngineers']);
    Route::get('sales-engineer-assignment/assignable-users', [SalesEngineerAssignmentController::class, 'assignableUsers']);
    Route::get('sales-engineer-assignment/{managerUserId}/assignments', [SalesEngineerAssignmentController::class, 'index']);
    Route::put('sales-engineer-assignment/assignments', [SalesEngineerAssignmentController::class, 'upsert']);
    Route::delete('sales-engineer-assignment/{managerUserId}/assignments/{assignedUserId}', [SalesEngineerAssignmentController::class, 'destroy']);
});
