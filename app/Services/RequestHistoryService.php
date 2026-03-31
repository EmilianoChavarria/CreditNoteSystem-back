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

        $historyValues = $history->values();

        $timeline = $historyValues->map(function ($entry, $index) use ($historyValues) {
            $previous = $index > 0 ? $historyValues->get($index - 1) : null;
            $actionType = (string) $entry->actionType;

            $messages = [
                'created' => 'Solicitud creada en el flujo',
                'approved' => 'Paso aprobado',
                'rejected' => 'Paso rechazado',
                'routed' => 'Solicitud enviada al siguiente paso',
                'routed_back' => 'Solicitud regresada al paso anterior',
            ];

            return [
                'sequence' => $index + 1,
                'timestamp' => $entry->createdAt,
                'actionType' => $actionType,
                'message' => $messages[$actionType] ?? 'Evento del workflow',
                'comments' => $entry->comments,
                'step' => [
                    'id' => $entry->workflowStep?->id,
                    'name' => $entry->workflowStep?->stepName,
                    'order' => $entry->workflowStep?->stepOrder,
                ],
                'fromStep' => $actionType === 'routed' || $actionType === 'routed_back'
                    ? [
                        'id' => $previous?->workflowStep?->id,
                        'name' => $previous?->workflowStep?->stepName,
                        'order' => $previous?->workflowStep?->stepOrder,
                    ]
                    : null,
                'toStep' => $actionType === 'routed' || $actionType === 'routed_back'
                    ? [
                        'id' => $entry->workflowStep?->id,
                        'name' => $entry->workflowStep?->stepName,
                        'order' => $entry->workflowStep?->stepOrder,
                    ]
                    : null,
                'actionUser' => $entry->actionUser,
            ];
        })->values();

        $lastStepExecutionByStepId = $requestSteps->groupBy('workflowStepId')->map(static function ($executions) {
            return $executions->last();
        });

        $visitedStepIds = $requestSteps->pluck('workflowStepId')->unique()->values()->all();

        $steps = $allWorkflowSteps->map(function ($step) use ($visitedStepIds, $lastStepExecutionByStepId, $currentStep) {
            $latestExecution = $lastStepExecutionByStepId->get($step->id);

            return [
                'id' => $step->id,
                'stepName' => $step->stepName,
                'stepOrder' => $step->stepOrder,
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

        $totalSteps = $allWorkflowSteps->count();
        $currentStepOrder = $currentStep->workflowStep?->stepOrder;
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
