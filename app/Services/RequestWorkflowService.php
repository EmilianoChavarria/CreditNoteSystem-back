<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Request as RequestModel;
use App\Models\RequestClassification;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Workflow;
use App\Models\WorkflowRequestCurrentStep;
use App\Models\WorkflowRequestHistory;
use App\Models\WorkflowRequestStep;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RequestWorkflowService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    public function assignRequestToWorkflow(RequestModel $requestModel, int $actionUserId): void
    {
        $classification = RequestClassification::find($requestModel->classificationId);

        if (!$classification) {
            throw ValidationException::withMessages([
                'classificationId' => ['No existe la clasificacion seleccionada.'],
            ]);
        }

        $isTypeLinkedToClassification = $classification->requestTypes()
            ->where('id', $requestModel->requestTypeId)
            ->exists();

        if (!$isTypeLinkedToClassification) {
            throw ValidationException::withMessages([
                'classificationId' => ['La clasificacion no pertenece al tipo de solicitud indicado.'],
            ]);
        }

        $workflow = Workflow::query()
            ->where('requestTypeId', $requestModel->requestTypeId)
            ->where('classificationType', $classification->type)
            ->where('isActive', true)
            ->orderBy('id')
            ->first();

        if (!$workflow) {
            throw ValidationException::withMessages([
                'workflow' => ['No existe un workflow activo para el tipo de solicitud y la clasificacion.type.'],
            ]);
        }

        $initialStep = WorkflowStep::query()
            ->where('workflowId', $workflow->id)
            ->where('isInitialStep', true)
            ->orderBy('stepOrder')
            ->first();

        if (!$initialStep) {
            throw ValidationException::withMessages([
                'workflowStep' => ['El workflow seleccionado no tiene paso inicial configurado.'],
            ]);
        }

        $firstOperationalStep = $this->resolveFirstOperationalStep($workflow, (int) $initialStep->id);

        if (!$firstOperationalStep || (int) $firstOperationalStep->id === (int) $initialStep->id) {
            $assignedUserId = $this->resolveAssignedUserIdForStep($requestModel, $initialStep);

            $requestStep = WorkflowRequestStep::create([
                'requestId' => $requestModel->id,
                'workflowStepId' => $initialStep->id,
                'assignedRoleId' => $initialStep->roleId,
                'assignedUserId' => $assignedUserId,
                'status' => 'pending',
                'startedAt' => now(),
            ]);

            WorkflowRequestCurrentStep::updateOrCreate(
                ['requestId' => $requestModel->id],
                [
                    'workflowId' => $workflow->id,
                    'workflowStepId' => $initialStep->id,
                    'assignedRoleId' => $initialStep->roleId,
                    'assignedUserId' => $assignedUserId,
                    'status' => 'pending',
                ]
            );

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $requestStep->id,
                'requestId' => $requestModel->id,
                'workflowStepId' => $initialStep->id,
                'actionUserId' => $actionUserId,
                'actionType' => 'created',
                'comments' => 'Solicitud creada y asignada al flujo inicial.',
            ]);

            return;
        }

        $initialRequestStep = WorkflowRequestStep::create([
            'requestId' => $requestModel->id,
            'workflowStepId' => $initialStep->id,
            'assignedRoleId' => $initialStep->roleId,
            'assignedUserId' => $actionUserId,
            'status' => 'approved',
            'startedAt' => now(),
            'completedAt' => now(),
        ]);

        WorkflowRequestHistory::create([
            'requestWorkflowStepId' => $initialRequestStep->id,
            'requestId' => $requestModel->id,
            'workflowStepId' => $initialStep->id,
            'actionUserId' => $actionUserId,
            'actionType' => 'created',
            'comments' => 'Solicitud creada. El paso inicial se registró solo como referencia visual.',
        ]);

        $firstOperationalAssignedUserId = $this->resolveAssignedUserIdForStep($requestModel, $firstOperationalStep);

        $firstOperationalRequestStep = WorkflowRequestStep::create([
            'requestId' => $requestModel->id,
            'workflowStepId' => $firstOperationalStep->id,
            'assignedRoleId' => $firstOperationalStep->roleId,
            'assignedUserId' => $firstOperationalAssignedUserId,
            'status' => 'pending',
            'startedAt' => now(),
        ]);

        WorkflowRequestCurrentStep::updateOrCreate(
            ['requestId' => $requestModel->id],
            [
                'workflowId' => $workflow->id,
                'workflowStepId' => $firstOperationalStep->id,
                'assignedRoleId' => $firstOperationalStep->roleId,
                'assignedUserId' => $firstOperationalAssignedUserId,
                'status' => 'pending',
            ]
        );

        WorkflowRequestHistory::create([
            'requestWorkflowStepId' => $firstOperationalRequestStep->id,
            'requestId' => $requestModel->id,
            'workflowStepId' => $firstOperationalStep->id,
            'actionUserId' => $actionUserId,
            'actionType' => 'routed',
            'comments' => 'Solicitud iniciada en el primer paso operativo del flujo.',
        ]);
    }

    public function notifyAssignedUser(int $requestId): void
    {
        $currentStep = WorkflowRequestCurrentStep::query()
            ->where('requestId', $requestId)
            ->where('status', 'pending')
            ->first();

        if (!$currentStep || $currentStep->assignedUserId === null) {
            return;
        }

        $requestModel = RequestModel::query()
            ->with('requestType')
            ->find($requestId);

        if (!$requestModel) {
            return;
        }

        $this->notificationService->createAssignedRequestNotification($requestModel, (int) $currentStep->assignedUserId);
    }

    public function approve(int $requestId, mixed $authUser, bool $isAdmin, ?string $comments): array
    {
        return DB::transaction(function () use ($requestId, $authUser, $isAdmin, $comments) {
            $requestModel = RequestModel::query()->lockForUpdate()->find($requestId);

            if (!$requestModel) {
                return ['ok' => false, 'status' => 404, 'payload' => ['message' => 'Request no encontrada']];
            }

            $currentStep = WorkflowRequestCurrentStep::query()->where('requestId', $requestId)->lockForUpdate()->first();
            if (!$currentStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'La solicitud no tiene un paso actual asignado']];
            }

            if (!$isAdmin && ($currentStep->assignedUserId === null || (int) $currentStep->assignedUserId !== (int) $authUser->id)) {
                return ['ok' => false, 'status' => 403, 'payload' => ['message' => 'No tienes permisos para aprobar esta solicitud en el paso actual (asignación por usuario)']];
            }

            $activeRequestStep = WorkflowRequestStep::query()
                ->where('requestId', $requestId)
                ->where('workflowStepId', $currentStep->workflowStepId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$activeRequestStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No existe un paso pendiente para aprobar']];
            }

            $currentWorkflowStep = WorkflowStep::query()->find($currentStep->workflowStepId);
            if (!$currentWorkflowStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'El paso actual del workflow no existe']];
            }

            $activeRequestStep->update(['status' => 'approved', 'completedAt' => now()]);

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $activeRequestStep->id,
                'requestId' => $requestId,
                'workflowStepId' => $currentWorkflowStep->id,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'approved',
                'comments' => $comments,
            ]);

            $nextStep = $this->resolveNextStep($requestModel, $currentWorkflowStep);

            if (!$nextStep || (bool) $currentWorkflowStep->isFinalStep) {
                $currentStep->update(['status' => 'approved']);
                $requestModel->update(['status' => 'approved']);

                return [
                    'ok' => true,
                    'status' => 200,
                    'payload' => [
                        'message' => 'Solicitud aprobada y flujo finalizado',
                        'data' => $requestModel->refresh(),
                    ],
                    'notifyUserId' => null,
                ];
            }

            $nextAssignedUserId = $this->resolveAssignedUserIdForStep($requestModel, $nextStep);

            $nextRequestStep = WorkflowRequestStep::create([
                'requestId' => $requestId,
                'workflowStepId' => $nextStep->id,
                'assignedRoleId' => $nextStep->roleId,
                'assignedUserId' => $nextAssignedUserId,
                'status' => 'pending',
                'startedAt' => now(),
            ]);

            WorkflowRequestCurrentStep::updateOrCreate(
                ['requestId' => $requestId],
                [
                    'workflowId' => $currentStep->workflowId,
                    'workflowStepId' => $nextStep->id,
                    'assignedRoleId' => $nextStep->roleId,
                    'assignedUserId' => $nextAssignedUserId,
                    'status' => 'pending',
                ]
            );

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $nextRequestStep->id,
                'requestId' => $requestId,
                'workflowStepId' => $nextStep->id,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'routed',
                'comments' => 'Solicitud enviada al siguiente paso del flujo.',
            ]);

            $requestModel->update(['status' => 'pending']);

            return [
                'ok' => true,
                'status' => 200,
                'payload' => [
                    'message' => 'Solicitud aprobada y enviada al siguiente paso',
                    'data' => $requestModel->refresh(),
                ],
                'notifyUserId' => $nextAssignedUserId,
            ];
        });
    }

    public function reject(int $requestId, mixed $authUser, bool $isAdmin, string $comments): array
    {
        return DB::transaction(function () use ($requestId, $authUser, $isAdmin, $comments) {
            $requestModel = RequestModel::query()->lockForUpdate()->find($requestId);
            if (!$requestModel) {
                return ['ok' => false, 'status' => 404, 'payload' => ['message' => 'Request no encontrada']];
            }

            $currentStep = WorkflowRequestCurrentStep::query()->where('requestId', $requestId)->lockForUpdate()->first();
            if (!$currentStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'La solicitud no tiene un paso actual asignado']];
            }

            if (!$isAdmin && ($currentStep->assignedUserId === null || (int) $currentStep->assignedUserId !== (int) $authUser->id)) {
                return ['ok' => false, 'status' => 403, 'payload' => ['message' => 'No tienes permisos para rechazar esta solicitud en el paso actual (asignación por usuario)']];
            }

            $activeRequestStep = WorkflowRequestStep::query()
                ->where('requestId', $requestId)
                ->where('workflowStepId', $currentStep->workflowStepId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$activeRequestStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No existe un paso pendiente para rechazar']];
            }

            $currentWorkflowStep = WorkflowStep::query()->find($currentStep->workflowStepId);
            if (!$currentWorkflowStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'El paso actual del workflow no existe']];
            }

            if ((bool) $currentWorkflowStep->isInitialStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No se puede rechazar una solicitud que se encuentra en el paso inicial del flujo']];
            }

            $activeRequestStep->update(['status' => 'rejected', 'completedAt' => now()]);

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $activeRequestStep->id,
                'requestId' => $requestId,
                'workflowStepId' => $currentStep->workflowStepId,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'rejected',
                'comments' => $comments,
            ]);

            $previousStep = $this->resolvePreviousStepByOrder($currentWorkflowStep);
            if (!$previousStep) {
                $currentStep->update(['status' => 'rejected']);
                $requestModel->update(['status' => 'rejected']);

                return [
                    'ok' => true,
                    'status' => 200,
                    'payload' => [
                        'message' => 'Solicitud rechazada. No existe paso anterior para regresar',
                        'data' => $requestModel->refresh(),
                    ],
                    'notifyUserId' => null,
                ];
            }

            $previousAssignedUserId = $this->resolveAssignedUserIdForStep($requestModel, $previousStep);
            $previousRequestStep = WorkflowRequestStep::create([
                'requestId' => $requestId,
                'workflowStepId' => $previousStep->id,
                'assignedRoleId' => $previousStep->roleId,
                'assignedUserId' => $previousAssignedUserId,
                'status' => 'pending',
                'startedAt' => now(),
            ]);

            WorkflowRequestCurrentStep::updateOrCreate(
                ['requestId' => $requestId],
                [
                    'workflowId' => $currentStep->workflowId,
                    'workflowStepId' => $previousStep->id,
                    'assignedRoleId' => $previousStep->roleId,
                    'assignedUserId' => $previousAssignedUserId,
                    'status' => 'pending',
                ]
            );

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $previousRequestStep->id,
                'requestId' => $requestId,
                'workflowStepId' => $previousStep->id,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'routed_back',
                'comments' => 'Solicitud regresada al paso anterior del flujo.',
            ]);

            $requestModel->update(['status' => 'pending']);

            return [
                'ok' => true,
                'status' => 200,
                'payload' => [
                    'message' => 'Solicitud rechazada y regresada al paso anterior',
                    'data' => $requestModel->refresh(),
                ],
                'notifyUserId' => $previousAssignedUserId,
            ];
        });
    }

    public function approveMass(array $requestIds, mixed $authUser, bool $isAdmin, ?string $comments): array
    {
        $approvedIds = [];
        $failed = [];
        $notificationsByUser = [];

        foreach ($requestIds as $requestId) {
            $result = $this->approve((int) $requestId, $authUser, $isAdmin, $comments);

            if ($result['ok']) {
                $approvedIds[] = (int) $requestId;

                $notifyUserId = $result['notifyUserId'] ?? null;
                if (!empty($notifyUserId)) {
                    $requestNumber = (string) (RequestModel::query()->where('id', $requestId)->value('requestNumber') ?? $requestId);
                    $notificationsByUser[(int) $notifyUserId][] = $requestNumber;
                }

                continue;
            }

            $failed[] = ['requestId' => (int) $requestId, 'reason' => (string) ($result['payload']['message'] ?? 'Error')];
        }

        foreach ($notificationsByUser as $userId => $requestNumbers) {
            $this->notificationService->createAssignedRequestsSummaryNotification((int) $userId, $requestNumbers);
        }

        return [
            'totalReceived' => count($requestIds),
            'totalApproved' => count($approvedIds),
            'totalFailed' => count($failed),
            'approvedRequestIds' => $approvedIds,
            'failedRequests' => $failed,
        ];
    }

    public function rejectMass(array $requestIds, mixed $authUser, bool $isAdmin, string $comments): array
    {
        $rejectedIds = [];
        $failed = [];
        $notificationsByUser = [];

        foreach ($requestIds as $requestId) {
            $result = $this->reject((int) $requestId, $authUser, $isAdmin, $comments);

            if ($result['ok']) {
                $rejectedIds[] = (int) $requestId;

                $notifyUserId = $result['notifyUserId'] ?? null;
                if (!empty($notifyUserId)) {
                    $requestNumber = (string) (RequestModel::query()->where('id', $requestId)->value('requestNumber') ?? $requestId);
                    $notificationsByUser[(int) $notifyUserId][] = $requestNumber;
                }

                continue;
            }

            $failed[] = ['requestId' => (int) $requestId, 'reason' => (string) ($result['payload']['message'] ?? 'Error')];
        }

        foreach ($notificationsByUser as $userId => $requestNumbers) {
            $this->notificationService->createAssignedRequestsSummaryNotification((int) $userId, $requestNumbers);
        }

        return [
            'totalReceived' => count($requestIds),
            'totalRejected' => count($rejectedIds),
            'totalFailed' => count($failed),
            'rejectedRequestIds' => $rejectedIds,
            'failedRequests' => $failed,
            'commentApplied' => $comments,
        ];
    }

    private function resolveNextStep(RequestModel $requestModel, WorkflowStep $currentStep): ?WorkflowStep
    {
        $transitions = WorkflowStepTransition::query()
            ->where('workflowId', $currentStep->workflowId)
            ->where('fromStepId', $currentStep->id)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($transitions->isEmpty()) {
            return $this->resolveNextStepByOrder($currentStep);
        }

        foreach ($transitions as $transition) {
            if ($this->matchesTransitionCondition($requestModel, $transition)) {
                $nextByTransition = WorkflowStep::query()
                    ->where('id', $transition->toStepId)
                    ->where('workflowId', $currentStep->workflowId)
                    ->first();

                if ($nextByTransition) {
                    return $nextByTransition;
                }
            }
        }

        return $this->resolveNextStepByOrder($currentStep);
    }

    private function resolveNextStepByOrder(WorkflowStep $currentStep): ?WorkflowStep
    {
        return WorkflowStep::query()
            ->where('workflowId', $currentStep->workflowId)
            ->where('stepOrder', '>', $currentStep->stepOrder)
            ->orderBy('stepOrder')
            ->orderBy('id')
            ->first();
    }

    private function resolvePreviousStepByOrder(WorkflowStep $currentStep): ?WorkflowStep
    {
        return WorkflowStep::query()
            ->where('workflowId', $currentStep->workflowId)
            ->where('stepOrder', '<', $currentStep->stepOrder)
            ->orderByDesc('stepOrder')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveFirstOperationalStep(Workflow $workflow, int $initialStepId): ?WorkflowStep
    {
        return WorkflowStep::query()
            ->where('workflowId', $workflow->id)
            ->where('id', '!=', $initialStepId)
            ->orderBy('stepOrder')
            ->orderBy('id')
            ->first();
    }

    private function matchesTransitionCondition(RequestModel $requestModel, mixed $transition): bool
    {
        if ($transition->conditionField === null || $transition->conditionField === '') {
            return true;
        }

        $left = data_get($requestModel, $transition->conditionField);
        $operator = (string) ($transition->conditionOperator ?? '==');
        $rightRaw = $transition->conditionValue;

        if (in_array($operator, ['>', '<', '>=', '<='], true)) {
            if (!is_numeric($left) || !is_numeric($rightRaw)) {
                return false;
            }

            $leftNumber = (float) $left;
            $rightNumber = (float) $rightRaw;

            return match ($operator) {
                '>' => $leftNumber > $rightNumber,
                '<' => $leftNumber < $rightNumber,
                '>=' => $leftNumber >= $rightNumber,
                '<=' => $leftNumber <= $rightNumber,
                default => false,
            };
        }

        $leftString = (string) $left;
        $rightString = (string) $rightRaw;

        return match ($operator) {
            '=', '==' => $leftString === $rightString,
            '!=', '<>' => $leftString !== $rightString,
            default => false,
        };
    }

    private function resolveAssignedUserIdForStep(RequestModel $requestModel, WorkflowStep $step): ?int
    {
        $step->loadMissing('role');
        $roleName = mb_strtoupper((string) optional($step->role)->roleName);

        if (str_contains($roleName, 'CS LEADER')) {
            $userId = $this->resolveCsLeaderAssignedUserId($requestModel);
            if ($userId !== null) {
                return $userId;
            }
        }

        if (str_contains($roleName, 'MANAGER')) {
            $userId = $this->resolveManagerAssignedUserId($requestModel);
            if ($userId !== null) {
                return $userId;
            }
        }

        return $this->resolveFirstUserByRoleId((int) $step->roleId);
    }

    private function resolveManagerAssignedUserId(RequestModel $requestModel): ?int
    {
        $customerId = (int) ($requestModel->customerId ?? 0);
        if ($customerId <= 0) {
            return null;
        }

        $customer = Customer::query()->where('idClient', $customerId)->first();
        $candidateUserId = $customer?->salesManagerId ? (int) $customer->salesManagerId : null;

        if ($candidateUserId !== null && $this->isActiveUser($candidateUserId)) {
            return $candidateUserId;
        }

        if (
            Schema::hasTable('clientes_tme700618rc7_ext')
            && Schema::hasColumn('clientes_tme700618rc7_ext', 'idCliente')
            && Schema::hasColumn('clientes_tme700618rc7_ext', 'salesManagerId')
        ) {
            $candidateUserId = DB::table('clientes_tme700618rc7_ext')
                ->where('idCliente', $customerId)
                ->value('salesManagerId');

            if ($candidateUserId !== null && $this->isActiveUser((int) $candidateUserId)) {
                return (int) $candidateUserId;
            }
        }

        return null;
    }

    private function resolveCsLeaderAssignedUserId(RequestModel $requestModel): ?int
    {
        $creatorUserId = (int) ($requestModel->userId ?? 0);
        if ($creatorUserId <= 0) {
            return null;
        }

        $leaderUserId = UserAssignment::query()
            ->where('assignedUserId', $creatorUserId)
            ->where('isActive', true)
            ->orderBy('id')
            ->value('leaderUserId');

        if ($leaderUserId === null) {
            return null;
        }

        $leader = User::with('role')
            ->where('id', (int) $leaderUserId)
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->first();

        if (!$leader) {
            return null;
        }

        $leaderRoleName = mb_strtoupper((string) optional($leader->role)->roleName);
        if (!str_contains($leaderRoleName, 'CS LEADER')) {
            return null;
        }

        return (int) $leader->id;
    }

    private function resolveFirstUserByRoleId(int $roleId): ?int
    {
        if ($roleId <= 0) {
            return null;
        }

        $user = User::query()
            ->where('roleId', $roleId)
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->orderBy('id')
            ->first();

        return $user ? (int) $user->id : null;
    }

    private function isActiveUser(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->exists();
    }
}