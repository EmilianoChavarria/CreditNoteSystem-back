<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkflowStepController extends Controller
{
    public function getAll()
    {
        $steps = WorkflowStep::with(['workflow', 'role'])
            ->orderBy('workflowId')
            ->orderBy('stepOrder')
            ->get();

        return response()->json(ApiResponse::success('Workflow steps', $steps));
    }

    public function getById(int $id)
    {
        $step = WorkflowStep::with(['workflow', 'role'])->find($id);

        if (!$step) {
            return response()->json(ApiResponse::error('Workflow step not found', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Workflow step', $step));
    }

    public function store(Request $request)
    {
        $workflowTable = (new Workflow())->getTable();
        $roleTable = (new Role())->getTable();

        $validator = Validator::make($request->all(), [
            'workflowId' => ['required', 'integer', 'exists:' . $workflowTable . ',id'],
            'stepName' => ['required', 'string', 'max:255'],
            'stepOrder' => ['required', 'integer', 'min:1'],
            'roleId' => ['required', 'integer', 'exists:' . $roleTable . ',id'],
            'isInitialStep' => ['nullable', 'boolean'],
            'isFinalStep' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validator->errors(), 422), 422);
        }

        $isInitialStep = $request->boolean('isInitialStep', false);
        $isFinalStep = $request->boolean('isFinalStep', false);

        if ($isInitialStep) {
            $initialStepExists = WorkflowStep::where('workflowId', (int) $request->input('workflowId'))
                ->where('isInitialStep', true)
                ->exists();

            if ($initialStepExists) {
                return response()->json(
                    ApiResponse::error('Invalid data', ['isInitialStep' => ['Ya existe un paso inicial para este workflow']], 422),
                    422
                );
            }
        }

        if ($isFinalStep) {
            $finalStepExists = WorkflowStep::where('workflowId', (int) $request->input('workflowId'))
                ->where('isFinalStep', true)
                ->exists();

            if ($finalStepExists) {
                return response()->json(
                    ApiResponse::error('Invalid data', ['isFinalStep' => ['Ya existe un paso final para este workflow']], 422),
                    422
                );
            }
        }

        $step = WorkflowStep::create([
            'workflowId' => $request->input('workflowId'),
            'stepName' => $request->input('stepName'),
            'stepOrder' => $request->input('stepOrder'),
            'roleId' => $request->input('roleId'),
            'isInitialStep' => $isInitialStep,
            'isFinalStep' => $isFinalStep,
        ]);

        return response()->json(
            ApiResponse::success('Workflow step created successfully', $step->load(['workflow', 'role']), 201),
            201
        );
    }

    public function update(Request $request, int $id)
    {
        $step = WorkflowStep::find($id);

        if (!$step) {
            return response()->json(ApiResponse::error('Workflow step not found', null, 404), 404);
        }

        $workflowTable = (new Workflow())->getTable();
        $roleTable = (new Role())->getTable();

        $validator = Validator::make($request->all(), [
            'workflowId' => ['sometimes', 'required', 'integer', 'exists:' . $workflowTable . ',id'],
            'stepName' => ['sometimes', 'required', 'string', 'max:255'],
            'stepOrder' => ['sometimes', 'required', 'integer', 'min:1'],
            'roleId' => ['sometimes', 'required', 'integer', 'exists:' . $roleTable . ',id'],
            'isInitialStep' => ['sometimes', 'nullable', 'boolean'],
            'isFinalStep' => ['sometimes', 'nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validator->errors(), 422), 422);
        }

        $targetWorkflowId = (int) $request->input('workflowId', $step->workflowId);
        $targetIsInitialStep = $request->has('isInitialStep')
            ? $request->boolean('isInitialStep')
            : (bool) $step->isInitialStep;
        $targetIsFinalStep = $request->has('isFinalStep')
            ? $request->boolean('isFinalStep')
            : (bool) $step->isFinalStep;

        if ($targetIsInitialStep) {
            $initialStepExists = WorkflowStep::where('workflowId', $targetWorkflowId)
                ->where('isInitialStep', true)
                ->where('id', '!=', $step->id)
                ->exists();

            if ($initialStepExists) {
                return response()->json(
                    ApiResponse::error('Invalid data', ['isInitialStep' => ['Ya existe un paso inicial para este workflow']], 422),
                    422
                );
            }
        }

        if ($targetIsFinalStep) {
            $finalStepExists = WorkflowStep::where('workflowId', $targetWorkflowId)
                ->where('isFinalStep', true)
                ->where('id', '!=', $step->id)
                ->exists();

            if ($finalStepExists) {
                return response()->json(
                    ApiResponse::error('Invalid data', ['isFinalStep' => ['Ya existe un paso final para este workflow']], 422),
                    422
                );
            }
        }

        $step->update($request->only([
            'workflowId',
            'stepName',
            'stepOrder',
            'roleId',
            'isInitialStep',
            'isFinalStep',
        ]));

        return response()->json(ApiResponse::success('Workflow step updated successfully', $step->load(['workflow', 'role'])));
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
