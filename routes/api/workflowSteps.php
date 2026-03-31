<?php

use App\Http\Controllers\Api\WorkflowStepController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('workflowsteps', [WorkflowStepController::class, 'getAll']);
    Route::get('workflowsteps/workflows', [WorkflowStepController::class, 'getAllWorkflowsWithSteps']);
    Route::get('workflowsteps/workflow/{workflowId}', [WorkflowStepController::class, 'getByWorkflowId']);
    Route::get('workflowsteps/{id}', [WorkflowStepController::class, 'getById']);
    Route::post('workflowsteps', [WorkflowStepController::class, 'store']);
    Route::put('workflowsteps/{id}', [WorkflowStepController::class, 'update']);
    Route::delete('workflowsteps/{id}', [WorkflowStepController::class, 'delete']);
});
