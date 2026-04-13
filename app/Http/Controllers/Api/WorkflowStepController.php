<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkflowSteps\StoreWorkflowStepRequest;
use App\Http\Requests\WorkflowSteps\UpdateWorkflowStepRequest;
use App\Http\Resources\WorkflowResource;
use App\Http\Resources\WorkflowStepResource;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowStepService;
use App\Support\ApiResponse;
use Illuminate\Validation\ValidationException;

class WorkflowStepController extends Controller
{
    public function __construct(
        private readonly WorkflowStepService $workflowStepService
    ) {
    }

    public function getAll()
    {
        $steps = WorkflowStep::with(['workflow', 'role', 'outgoingTransitions.toStep'])
            ->orderBy('workflowId')
            ->orderBy('stepOrder')
            ->get();

        return response()->json(ApiResponse::success('Workflow steps', WorkflowStepResource::collection($steps)));
    }

    public function getById(int $id)
    {
        $step = WorkflowStep::with(['workflow', 'role', 'outgoingTransitions.toStep'])->find($id);

        if (!$step) {
            return response()->json(ApiResponse::error('Workflow step not found', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Workflow step', WorkflowStepResource::make($step)));
    }

    public function getByWorkflowId(int $workflowId)
    {
        $workflow = Workflow::with(['requestType', 'classification'])->find($workflowId);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        $steps = WorkflowStep::with(['role', 'outgoingTransitions.toStep'])
            ->where('workflowId', $workflowId)
            ->orderBy('stepOrder')
            ->get();

        return response()->json(ApiResponse::success('Workflow with steps', [
            'workflow' => WorkflowResource::make($workflow),
            'steps' => WorkflowStepResource::collection($steps),
        ]));
    }

    public function getAllWorkflowsWithSteps()
    {
        $workflows = Workflow::with([
            'requestType',
            'classification',
            'steps' => function ($query) {
                $query->with(['role', 'outgoingTransitions.toStep'])
                    ->orderBy('stepOrder');
            },
        ])
            ->where('isActive', 1)
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Workflows with steps', WorkflowResource::collection($workflows)));
    }

    public function store(StoreWorkflowStepRequest $request)
    {
        try {
            $step = $this->workflowStepService->create($request->validated());
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Invalid data', $e->errors(), 422), 422);
        }

        return response()->json(
            ApiResponse::success('Workflow step created successfully', WorkflowStepResource::make($step), 201),
            201
        );
    }

    public function update(UpdateWorkflowStepRequest $request, int $id)
    {
        $step = WorkflowStep::find($id);

        if (!$step) {
            return response()->json(ApiResponse::error('Workflow step not found', null, 404), 404);
        }

        try {
            $step = $this->workflowStepService->update($step, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Invalid data', $e->errors(), 422), 422);
        }

        return response()->json(ApiResponse::success('Workflow step updated successfully', WorkflowStepResource::make($step)));
    }

    public function delete(int $id)
    {
        $step = WorkflowStep::find($id);

        if (!$step) {
            return response()->json(ApiResponse::error('Workflow step not found', null, 404), 404);
        }

        $step->delete();

        return response()->json(ApiResponse::success('Workflow step deleted successfully'));
    }

}
