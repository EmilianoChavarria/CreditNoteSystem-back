<?php

namespace App\Services;

use App\Models\Customer;
use App\Services\BanxicoService;
use App\Models\Request as RequestModel;
use App\Models\RequestClassification;
use App\Models\User;
use App\Models\UserAssignment;
use App\Models\Workflow;
use App\Models\WorkflowRequestCurrentStep;
use App\Models\WorkflowRequestHistory;
use App\Models\WorkflowRequestStep;
use App\Models\WorkflowStep;
use App\Models\Role;
use App\Models\WorkflowStepTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RequestWorkflowService
{
    private const AMOUNT_FIELDS = [
        'totalAmount', 'amount', 'replenishmentAmount', 'replenishmentTotal',
        'warehouseAmount', 'warehouseTotal',
    ];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly BanxicoService $banxicoService,
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

            if ($requestModel->status === 'cancelled') {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No se puede aprobar una solicitud cancelada']];
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

            $nextStep = $this->resolveNextStep($requestModel, $currentWorkflowStep);
            $isFinalStep = !$nextStep || (bool) $currentWorkflowStep->isFinalStep;

            if (!$isFinalStep) {
                $nextAssignedUserId = $this->resolveAssignedUserIdForStep($requestModel, $nextStep);
                if ($nextAssignedUserId === null) {
                    $nextRoleName = mb_strtoupper((string) optional($nextStep->role)->roleName);
                    $message = str_contains($nextRoleName, 'CS LEADER')
                        ? 'No se puede avanzar la solicitud porque el solicitante no tiene un CS Leader asignado.'
                        : 'No se encontró un usuario disponible para el siguiente paso del flujo "' . $nextStep->stepName . '". No se puede avanzar la solicitud.';

                    return ['ok' => false, 'status' => 422, 'payload' => ['message' => $message]];
                }
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

            if ($isFinalStep) {
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

            if ($requestModel->status === 'cancelled') {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No se puede rechazar una solicitud cancelada']];
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

    public function cancel(int $requestId, mixed $authUser, bool $isAdmin, ?string $comments): array
    {
        return DB::transaction(function () use ($requestId, $authUser, $isAdmin, $comments) {
            $requestModel = RequestModel::query()->lockForUpdate()->find($requestId);

            if (!$requestModel) {
                return ['ok' => false, 'status' => 404, 'payload' => ['message' => 'Request no encontrada']];
            }

            $nonCancellableStatuses = ['approved', 'rejected', 'cancelled', 'draft'];
            if (in_array($requestModel->status, $nonCancellableStatuses, true)) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No se puede cancelar una solicitud con estatus "' . $requestModel->status . '"']];
            }

            if (!$isAdmin && (int) $requestModel->userId !== (int) $authUser->id) {
                return ['ok' => false, 'status' => 403, 'payload' => ['message' => 'No tienes permisos para cancelar esta solicitud']];
            }

            $currentStep = WorkflowRequestCurrentStep::query()
                ->where('requestId', $requestId)
                ->lockForUpdate()
                ->first();

            $activeRequestStep = WorkflowRequestStep::query()
                ->where('requestId', $requestId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $stepForHistory = $activeRequestStep ?? WorkflowRequestStep::query()
                ->where('requestId', $requestId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($activeRequestStep) {
                $activeRequestStep->update(['status' => 'cancelled', 'completedAt' => now()]);
            }

            if ($stepForHistory) {
                WorkflowRequestHistory::create([
                    'requestWorkflowStepId' => $stepForHistory->id,
                    'requestId' => $requestId,
                    'workflowStepId' => $stepForHistory->workflowStepId,
                    'actionUserId' => (int) $authUser->id,
                    'actionType' => 'cancelled',
                    'comments' => $comments,
                ]);
            }

            if ($currentStep) {
                $currentStep->update(['status' => 'cancelled']);
            }

            $requestModel->update(['status' => 'cancelled']);

            return [
                'ok' => true,
                'status' => 200,
                'payload' => [
                    'message' => 'Solicitud cancelada correctamente',
                    'data' => $requestModel->refresh(),
                ],
            ];
        });
    }

    public function sendBack(int $requestId, int $targetWorkflowStepId, mixed $authUser, bool $isAdmin, string $comments): array
    {
        return DB::transaction(function () use ($requestId, $targetWorkflowStepId, $authUser, $isAdmin, $comments) {
            $requestModel = RequestModel::query()->lockForUpdate()->find($requestId);

            if (!$requestModel) {
                return ['ok' => false, 'status' => 404, 'payload' => ['message' => 'Request no encontrada']];
            }

            if (in_array($requestModel->status, ['approved', 'rejected', 'cancelled', 'draft'], true)) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'No se puede regresar una solicitud con estatus "' . $requestModel->status . '"']];
            }

            $currentStep = WorkflowRequestCurrentStep::query()
                ->where('requestId', $requestId)
                ->lockForUpdate()
                ->first();

            if (!$currentStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'La solicitud no tiene un paso actual asignado']];
            }

            if (!$isAdmin && ($currentStep->assignedUserId === null || (int) $currentStep->assignedUserId !== (int) $authUser->id)) {
                return ['ok' => false, 'status' => 403, 'payload' => ['message' => 'No tienes permisos para regresar esta solicitud en el paso actual']];
            }

            $currentWorkflowStep = WorkflowStep::query()->find($currentStep->workflowStepId);

            if (!$currentWorkflowStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'El paso actual del workflow no existe']];
            }

            $targetWorkflowStep = WorkflowStep::query()
                ->where('id', $targetWorkflowStepId)
                ->where('workflowId', $currentWorkflowStep->workflowId)
                ->where('stepOrder', '<', $currentWorkflowStep->stepOrder)
                ->first();

            if (!$targetWorkflowStep) {
                return ['ok' => false, 'status' => 422, 'payload' => ['message' => 'El paso destino no es válido. Debe ser un paso anterior del mismo flujo de trabajo']];
            }

            $activeRequestStep = WorkflowRequestStep::query()
                ->where('requestId', $requestId)
                ->where('workflowStepId', $currentStep->workflowStepId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($activeRequestStep) {
                $activeRequestStep->update(['status' => 'sent_back', 'completedAt' => now()]);
            }

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $activeRequestStep?->id ?? WorkflowRequestStep::query()
                    ->where('requestId', $requestId)
                    ->orderByDesc('id')
                    ->value('id'),
                'requestId' => $requestId,
                'workflowStepId' => $currentWorkflowStep->id,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'sent_back',
                'comments' => $comments,
            ]);

            $targetAssignedUserId = $this->resolveAssignedUserIdForStep($requestModel, $targetWorkflowStep);

            $targetRequestStep = WorkflowRequestStep::create([
                'requestId' => $requestId,
                'workflowStepId' => $targetWorkflowStep->id,
                'assignedRoleId' => $targetWorkflowStep->roleId,
                'assignedUserId' => $targetAssignedUserId,
                'status' => 'pending',
                'startedAt' => now(),
            ]);

            WorkflowRequestCurrentStep::updateOrCreate(
                ['requestId' => $requestId],
                [
                    'workflowId' => $currentStep->workflowId,
                    'workflowStepId' => $targetWorkflowStep->id,
                    'assignedRoleId' => $targetWorkflowStep->roleId,
                    'assignedUserId' => $targetAssignedUserId,
                    'status' => 'pending',
                ]
            );

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $targetRequestStep->id,
                'requestId' => $requestId,
                'workflowStepId' => $targetWorkflowStep->id,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'routed_back',
                'comments' => 'Solicitud regresada al paso: ' . $targetWorkflowStep->stepName,
            ]);

            $requestModel->update(['status' => 'pending']);

            return [
                'ok' => true,
                'status' => 200,
                'payload' => [
                    'message' => 'Solicitud regresada al paso: ' . $targetWorkflowStep->stepName,
                    'data' => $requestModel->refresh(),
                ],
                'notifyUserId' => $targetAssignedUserId,
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

        $field    = (string) $transition->conditionField;
        $left     = data_get($requestModel, $field);
        $operator = (string) ($transition->conditionOperator ?? '==');
        $rightRaw = $transition->conditionValue;

        if (in_array($operator, ['>', '<', '>=', '<='], true)) {
            if (!is_numeric($left) || !is_numeric($rightRaw)) {
                return false;
            }

            $leftNumber  = (float) $left;
            $rightNumber = (float) $rightRaw;

            // Convert amount fields to USD before comparing so condition values
            // are always expressed in USD regardless of request currency.
            if (in_array($field, self::AMOUNT_FIELDS, true)) {
                $currency = mb_strtoupper((string) ($requestModel->currency ?? ''));
                if ($currency === 'MXN') {
                    $rate = $this->banxicoService->getCurrentUsdRate();
                    if ($rate > 0) {
                        $leftNumber = $leftNumber / $rate;
                    }
                }
            }

            return match ($operator) {
                '>'  => $leftNumber > $rightNumber,
                '<'  => $leftNumber < $rightNumber,
                '>=' => $leftNumber >= $rightNumber,
                '<=' => $leftNumber <= $rightNumber,
                default => false,
            };
        }

        $leftString  = (string) $left;
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

        if (str_contains($roleName, 'REQUESTER')) {
            $creatorId = (int) ($requestModel->userId ?? 0);
            return $creatorId > 0 ? $creatorId : null;
        }

        if (str_contains($roleName, 'CS LEADER')) {
            return $this->resolveCsLeaderAssignedUserId($requestModel);
        }

        if (str_contains($roleName, 'MANAGER')) {
            return $this->resolveManagerAssignedUserId($requestModel);
        }

        return $this->resolveFirstUserByRoleId((int) $step->roleId);
    }

    private function resolveManagerAssignedUserId(RequestModel $requestModel): ?int
    {
        $customerId = (int) ($requestModel->customerId ?? 0);
        if ($customerId <= 0) {
            return null;
        }

        $classification = RequestClassification::find($requestModel->classificationId);
        $classificationType = mb_strtolower((string) ($classification?->type ?? ''));

        $managerColumn = match (true) {
            str_contains($classificationType, 'finance')    => 'financeManagerId',
            str_contains($classificationType, 'marketing')  => 'marketingManagerId',
            str_contains($classificationType, 'customer')   => 'salesManagerId',
            default                                         => 'salesManagerId',
        };

        $customer = Customer::query()->where('idClient', $customerId)->first();
        $candidateUserId = $customer?->{$managerColumn} ? (int) $customer->{$managerColumn} : null;

        if ($candidateUserId !== null && $this->isActiveUser($candidateUserId)) {
            return $candidateUserId;
        }

        $extTable = 'clientes_TME700618RC7_ext';
        if (
            Schema::connection('invoices')->hasTable($extTable)
            && Schema::connection('invoices')->hasColumn($extTable, 'idCliente')
            && Schema::connection('invoices')->hasColumn($extTable, $managerColumn)
        ) {
            $candidateUserId = DB::connection('invoices')->table($extTable)
                ->where('idCliente', $customerId)
                ->value($managerColumn);

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

        if ($user) {
            Log::info('[resolveFirstUserByRoleId] user found by primary role', ['roleId' => $roleId, 'userId' => $user->id]);
            return (int) $user->id;
        }

        $equivalentroleid = Role::query()->where('id', $roleId)->value('equivalentroleid');

        Log::info('[resolveFirstUserByRoleId] no primary user, checking equivalent', [
            'roleId'          => $roleId,
            'equivalentroleid' => $equivalentroleid,
        ]);

        if ($equivalentroleid) {
            $fallbackUser = User::query()
                ->where('roleId', (int) $equivalentroleid)
                ->where('isActive', true)
                ->whereNull('deletedAt')
                ->orderBy('id')
                ->first();

            Log::info('[resolveFirstUserByRoleId] fallback result via role equivalentroleid', [
                'equivalentroleid' => $equivalentroleid,
                'fallbackUserId'   => $fallbackUser?->id,
            ]);

            if ($fallbackUser) {
                return (int) $fallbackUser->id;
            }
        }

        $reverseRoleIds = Role::query()
            ->where('equivalentroleid', $roleId)
            ->pluck('id');

        Log::info('[resolveFirstUserByRoleId] checking reverse equivalent roles', [
            'roleId'         => $roleId,
            'reverseRoleIds' => $reverseRoleIds,
        ]);

        if ($reverseRoleIds->isEmpty()) {
            return null;
        }

        $reverseUser = User::query()
            ->whereIn('roleId', $reverseRoleIds)
            ->where('isActive', true)
            ->whereNull('deletedAt')
            ->orderBy('id')
            ->first();

        Log::info('[resolveFirstUserByRoleId] reverse equivalent result', [
            'reverseUserId' => $reverseUser?->id,
        ]);

        return $reverseUser ? (int) $reverseUser->id : null;
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