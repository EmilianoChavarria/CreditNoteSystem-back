<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\WorkflowRequestCurrentStep;
use App\Models\WorkflowRequestHistory;
use App\Models\WorkflowRequestStep;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RequestHistoryService
{
    public function getHistoryByRequestId(int $requestId): array
    {
        $request = RequestModel::with([
            'requestType:id,name',
            'user:id,fullName,email,roleId',
            'reason:id,name',
            'classification:id,name,type',
        ])->find($requestId);

        if (!$request) {
            throw (new ModelNotFoundException())->setModel(RequestModel::class, [$requestId]);
        }

        $currentStep = WorkflowRequestCurrentStep::with([
            'workflowStep:id,workflowId,stepName,stepOrder,roleId,isInitialStep,isFinalStep',
            'workflowStep.role:id,roleName',
            'assignedRole:id,roleName',
            'assignedUser:id,fullName,email,roleId',
        ])->where('requestId', $requestId)->first();

        if (!$currentStep) {
            return [
                'request' => $request,
                'workflow' => null,
                'progress' => [
                    'currentStepOrder' => null,
                    'totalSteps' => 0,
                    'percent' => 0,
                ],
                'steps' => [],
                'history' => [],
                'currentStep' => null,
            ];
        }

        $allWorkflowSteps = WorkflowStep::with(['role:id,roleName'])
            ->where('workflowId', $currentStep->workflowId)
            ->orderBy('stepOrder')
            ->orderBy('id')
            ->get();

        $initialStep = $allWorkflowSteps->firstWhere('isInitialStep', true);
        $actionableSteps = $initialStep
            ? $allWorkflowSteps->reject(fn ($step) => (int) $step->id === (int) $initialStep->id)->values()
            : $allWorkflowSteps->values();

        $actionableOrderByStepId = $actionableSteps->mapWithKeys(function ($step, int $index) {
            return [(int) $step->id => $index + 1];
        })->all();

        $requestSteps = WorkflowRequestStep::with([
            'workflowStep:id,workflowId,stepName,stepOrder,roleId,isInitialStep,isFinalStep',
            'assignedRole:id,roleName',
            'assignedUser:id,fullName,email,roleId',
        ])
            ->where('requestId', $requestId)
            ->orderBy('startedAt')
            ->orderBy('id')
            ->get();

        $history = WorkflowRequestHistory::with([
            'workflowStep:id,workflowId,stepName,stepOrder,roleId,isInitialStep,isFinalStep',
            'actionUser:id,fullName,email,roleId',
            'requestStep:id,requestId,workflowStepId,assignedRoleId,assignedUserId,status,startedAt,completedAt',
            'requestStep.assignedUser:id,fullName,email,roleId',
        ])
            ->where('requestId', $requestId)
            ->orderBy('createdAt')
            ->orderBy('id')
            ->get();

        $messages = [
            'created'    => 'Solicitud creada en el flujo',
            'approved'   => 'Paso aprobado',
            'rejected'   => 'Paso rechazado',
            'routed_back' => 'Solicitud regresada al paso anterior',
        ];

        $decisiones = $history
            ->filter(fn ($entry) => (string) $entry->actionType !== 'routed')
            ->values();

        $timeline = $decisiones->map(function ($entry, $index) use ($messages) {
            $actionType = (string) $entry->actionType;

            return [
                'sequence'   => $index + 1,
                'timestamp'  => $entry->createdAt,
                'actionType' => $actionType,
                'message'    => $messages[$actionType] ?? 'Evento del workflow',
                'comments'   => $entry->comments,
                'step'       => [
                    'id'    => $entry->workflowStep?->id,
                    'name'  => $entry->workflowStep?->stepName,
                    'order' => $entry->workflowStep?->stepOrder,
                ],
                'actionUser' => $entry->actionUser,
            ];
        })->values();

        $timeline->push([
            'sequence'   => $timeline->count() + 1,
            'timestamp'  => $currentStep->startedAt ?? null,
            'actionType' => 'current',
            'message'    => 'Paso actual',
            'comments'   => null,
            'step'       => [
                'id'    => $currentStep->workflowStep?->id,
                'name'  => $currentStep->workflowStep?->stepName,
                'order' => $currentStep->workflowStep?->stepOrder,
            ],
            'actionUser' => $currentStep->assignedUser,
        ]);

        $lastStepExecutionByStepId = $requestSteps->groupBy('workflowStepId')->map(static function ($executions) {
            return $executions->last();
        });

        $visitedStepIds = $requestSteps->pluck('workflowStepId')->unique()->values()->all();

        $steps = $allWorkflowSteps->map(function ($step) use ($visitedStepIds, $lastStepExecutionByStepId, $currentStep, $actionableOrderByStepId) {
            $latestExecution = $lastStepExecutionByStepId->get($step->id);

            return [
                'id' => $step->id,
                'stepName' => $step->stepName,
                'stepOrder' => $step->stepOrder,
            'effectiveOrder' => $actionableOrderByStepId[(int) $step->id] ?? null,
                'role' => $step->role,
                'isInitialStep' => (bool) $step->isInitialStep,
                'isFinalStep' => (bool) $step->isFinalStep,
                'isCurrent' => (int) $currentStep->workflowStepId === (int) $step->id,
                'wasVisited' => in_array($step->id, $visitedStepIds, true),
                'latestStatus' => $latestExecution?->status,
                'latestStartedAt' => $latestExecution?->startedAt,
                'latestCompletedAt' => $latestExecution?->completedAt,
            ];
        })->values();

        $totalSteps = max(1, $actionableSteps->count());
        $currentStepOrder = $actionableOrderByStepId[(int) $currentStep->workflowStepId] ?? 1;
        $percent = 0;

        if ($totalSteps > 0 && $currentStepOrder) {
            $percent = (int) floor(($currentStepOrder / $totalSteps) * 100);
        }

        return [
            'request' => $request,
            'workflow' => [
                'id' => $currentStep->workflowId,
                'name' => $currentStep->workflow?->workflowName,
            ],
            'progress' => [
                'currentStepOrder' => $currentStepOrder,
                'totalSteps' => $totalSteps,
                'percent' => $percent,
            ],
            'steps' => $steps,
            'history' => $history,
            'timeline' => $timeline,
            'currentStep' => $currentStep,
        ];
    }
}
