<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Services\WorkflowService;
use App\Support\ApiResponse;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService
    ) {
    }

    public function getAll()
    {
        $workflows = Workflow::with(['requestType', 'classification'])->get();

        return response()->json(ApiResponse::success('Workflows', WorkflowResource::collection($workflows)));
    }

    public function getById($id)
    {
        $workflow = Workflow::with(['requestType', 'classification'])->find($id);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Workflow', WorkflowResource::make($workflow)));
    }

    public function store(StoreWorkflowRequest $request)
    {
        $workflow = $this->workflowService->create($request->validated());

        return response()->json(
            ApiResponse::success('Workflow created successfully', WorkflowResource::make($workflow), 201),
            201
        );
    }

    public function update(UpdateWorkflowRequest $request, $id)
    {
        $workflow = Workflow::find($id);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        $workflow = $this->workflowService->update($workflow, $request->validated());

        return response()->json(ApiResponse::success('Workflow updated successfully', WorkflowResource::make($workflow)));
    }

    public function delete($id)
    {
        $workflow = Workflow::find($id);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        $workflow->delete();

        return response()->json(ApiResponse::success('Workflow deleted successfully'));
    }
}
