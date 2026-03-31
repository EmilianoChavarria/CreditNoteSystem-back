<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkflowStepController extends Controller
{
    public function getAll()
    {
        $steps = WorkflowStep::with(['workflow', 'role', 'outgoingTransitions.toStep'])
            ->orderBy('workflowId')
            ->orderBy('stepOrder')
            ->get();

        return response()->json(ApiResponse::success('Workflow steps', $steps));
    }

    public function getById(int $id)
    {
        $step = WorkflowStep::with(['workflow', 'role', 'outgoingTransitions.toStep'])->find($id);

        if (!$step) {
            return response()->json(ApiResponse::error('Workflow step not found', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Workflow step', $step));
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
            'workflow' => $workflow,
            'steps' => $steps,
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
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Workflows with steps', $workflows));
    }

    public function store(Request $request)
    {
        $workflowTable = (new Workflow())->getTable();
        $roleTable = (new Role())->getTable();
        $stepTable = (new WorkflowStep())->getTable();

        $validator = Validator::make($request->all(), [
            'workflowId' => ['required', 'integer', 'exists:' . $workflowTable . ',id'],
            'stepName' => ['required', 'string', 'max:255'],
            'stepOrder' => ['required', 'integer', 'min:1'],
            'roleId' => ['required', 'integer', 'exists:' . $roleTable . ',id'],
            'isInitialStep' => ['nullable', 'boolean'],
            'isFinalStep' => ['nullable', 'boolean'],
            'transitions' => ['sometimes', 'array'],
            'transitions.*.toStepId' => ['required', 'integer', 'exists:' . $stepTable . ',id'],
            'transitions.*.conditionField' => ['nullable', 'string', 'max:100'],
            'transitions.*.conditionOperator' => ['nullable', 'string', 'max:20'],
            'transitions.*.conditionValue' => ['nullable', 'string', 'max:100'],
            'transitions.*.priority' => ['nullable', 'integer', 'min:1'],
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

        try {
            $step = DB::transaction(function () use ($request, $isInitialStep, $isFinalStep) {
                $workflowId = (int) $request->input('workflowId');
                $requestedStepOrder = (int) $request->input('stepOrder');

                $maxStepOrder = (int) WorkflowStep::where('workflowId', $workflowId)
                    ->lockForUpdate()
                    ->max('stepOrder');

                $stepOrderToInsert = min($requestedStepOrder, $maxStepOrder + 1);
                $effectiveIsInitialStep = $maxStepOrder === 0 ? true : $isInitialStep;

                WorkflowStep::where('workflowId', $workflowId)
                    ->where('stepOrder', '>=', $stepOrderToInsert)
                    ->increment('stepOrder');

                $createdStep = WorkflowStep::create([
                    'workflowId' => $workflowId,
                    'stepName' => $request->input('stepName'),
                    'stepOrder' => $stepOrderToInsert,
                    'roleId' => $request->input('roleId'),
                    'isInitialStep' => $effectiveIsInitialStep,
                    'isFinalStep' => $isFinalStep,
                ]);

                if ($request->filled('transitions')) {
                    $this->syncTransitionsForStep($createdStep, (array) $request->input('transitions'), false);
                }

                return $createdStep;
            });
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Invalid data', $e->errors(), 422), 422);
        }

        return response()->json(
            ApiResponse::success('Workflow step created successfully', $step->load(['workflow', 'role', 'outgoingTransitions.toStep']), 201),
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
        $stepTable = (new WorkflowStep())->getTable();

        $validator = Validator::make($request->all(), [
            'workflowId' => ['sometimes', 'required', 'integer', 'exists:' . $workflowTable . ',id'],
            'stepName' => ['sometimes', 'required', 'string', 'max:255'],
            'stepOrder' => ['sometimes', 'required', 'integer', 'min:1'],
            'roleId' => ['sometimes', 'required', 'integer', 'exists:' . $roleTable . ',id'],
            'isInitialStep' => ['sometimes', 'nullable', 'boolean'],
            'isFinalStep' => ['sometimes', 'nullable', 'boolean'],
            'transitions' => ['sometimes', 'array'],
            'transitions.*.toStepId' => ['required', 'integer', 'exists:' . $stepTable . ',id'],
            'transitions.*.conditionField' => ['nullable', 'string', 'max:100'],
            'transitions.*.conditionOperator' => ['nullable', 'string', 'max:20'],
            'transitions.*.conditionValue' => ['nullable', 'string', 'max:100'],
            'transitions.*.priority' => ['nullable', 'integer', 'min:1'],
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

        try {
            DB::transaction(function () use ($request, $step) {
                $step->update($request->only([
                    'workflowId',
                    'stepName',
                    'stepOrder',
                    'roleId',
                    'isInitialStep',
                    'isFinalStep',
                ]));

                if ($request->has('transitions')) {
                    $this->syncTransitionsForStep($step, (array) $request->input('transitions'), true);
                }
            });
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Invalid data', $e->errors(), 422), 422);
        }

        return response()->json(ApiResponse::success('Workflow step updated successfully', $step->load(['workflow', 'role', 'outgoingTransitions.toStep'])));
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

    /**
     * @param array<int, array<string, mixed>> $transitions
     */
    private function syncTransitionsForStep(WorkflowStep $step, array $transitions, bool $replaceExisting): void
    {
        if ($replaceExisting) {
            WorkflowStepTransition::where('fromStepId', $step->id)->delete();
        }

        foreach ($transitions as $transition) {
            $toStepId = (int) ($transition['toStepId'] ?? 0);

            if ($toStepId <= 0) {
                continue;
            }

            $targetStep = WorkflowStep::where('id', $toStepId)
                ->where('workflowId', $step->workflowId)
                ->first();

            if (!$targetStep) {
                throw ValidationException::withMessages([
                    'transitions' => ['El toStepId debe pertenecer al mismo workflow'],
                ]);
            }

            if ($targetStep->id === $step->id) {
                throw ValidationException::withMessages([
                    'transitions' => ['No se permite una transición al mismo paso'],
                ]);
            }

            WorkflowStepTransition::create([
                'workflowId' => $step->workflowId,
                'fromStepId' => $step->id,
                'toStepId' => $targetStep->id,
                'conditionField' => $transition['conditionField'] ?? null,
                'conditionOperator' => $transition['conditionOperator'] ?? null,
                'conditionValue' => $transition['conditionValue'] ?? null,
                'priority' => (int) ($transition['priority'] ?? 1),
            ]);
        }
    }
}
