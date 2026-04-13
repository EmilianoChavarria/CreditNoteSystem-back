<?php

namespace App\Services;

use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowStepService
{
    public function create(array $data): WorkflowStep
    {
        $workflowId = (int) $data['workflowId'];
        $isInitialStep = (bool) ($data['isInitialStep'] ?? false);
        $isFinalStep = (bool) ($data['isFinalStep'] ?? false);

        $this->ensureSingleEdgeSteps($workflowId, null, $isInitialStep, $isFinalStep);

        $step = DB::transaction(function () use ($data, $workflowId, $isInitialStep, $isFinalStep) {
            $requestedStepOrder = (int) $data['stepOrder'];

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
                'stepName' => $data['stepName'],
                'stepOrder' => $stepOrderToInsert,
                'roleId' => $data['roleId'],
                'isInitialStep' => $effectiveIsInitialStep,
                'isFinalStep' => $isFinalStep,
            ]);

            if (isset($data['transitions']) && is_array($data['transitions'])) {
                $this->syncTransitionsForStep($createdStep, $data['transitions'], false);
            }

            return $createdStep;
        });

        return $step->load(['workflow', 'role', 'outgoingTransitions.toStep']);
    }

    public function update(WorkflowStep $step, array $data): WorkflowStep
    {
        $targetWorkflowId = (int) ($data['workflowId'] ?? $step->workflowId);
        $targetIsInitialStep = array_key_exists('isInitialStep', $data)
            ? (bool) $data['isInitialStep']
            : (bool) $step->isInitialStep;
        $targetIsFinalStep = array_key_exists('isFinalStep', $data)
            ? (bool) $data['isFinalStep']
            : (bool) $step->isFinalStep;

        $this->ensureSingleEdgeSteps($targetWorkflowId, (int) $step->id, $targetIsInitialStep, $targetIsFinalStep);

        DB::transaction(function () use ($step, $data) {
            $step->update([
                'workflowId' => $data['workflowId'] ?? $step->workflowId,
                'stepName' => $data['stepName'] ?? $step->stepName,
                'stepOrder' => $data['stepOrder'] ?? $step->stepOrder,
                'roleId' => $data['roleId'] ?? $step->roleId,
                'isInitialStep' => array_key_exists('isInitialStep', $data) ? (bool) $data['isInitialStep'] : $step->isInitialStep,
                'isFinalStep' => array_key_exists('isFinalStep', $data) ? (bool) $data['isFinalStep'] : $step->isFinalStep,
            ]);

            if (array_key_exists('transitions', $data) && is_array($data['transitions'])) {
                $this->syncTransitionsForStep($step, $data['transitions'], true);
            }
        });

        return $step->load(['workflow', 'role', 'outgoingTransitions.toStep']);
    }

    private function ensureSingleEdgeSteps(int $workflowId, ?int $excludeStepId, bool $isInitial, bool $isFinal): void
    {
        if ($isInitial) {
            $query = WorkflowStep::where('workflowId', $workflowId)
                ->where('isInitialStep', true);

            if ($excludeStepId !== null) {
                $query->where('id', '!=', $excludeStepId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'isInitialStep' => ['Ya existe un paso inicial para este workflow'],
                ]);
            }
        }

        if ($isFinal) {
            $query = WorkflowStep::where('workflowId', $workflowId)
                ->where('isFinalStep', true);

            if ($excludeStepId !== null) {
                $query->where('id', '!=', $excludeStepId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'isFinalStep' => ['Ya existe un paso final para este workflow'],
                ]);
            }
        }
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

            if ((int) $targetStep->id === (int) $step->id) {
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