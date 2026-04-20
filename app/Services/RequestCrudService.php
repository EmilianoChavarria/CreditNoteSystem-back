<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\WorkflowRequestCurrentStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestCrudService
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function createRequest(array $data, mixed $authUser): RequestModel
    {
        $created = DB::transaction(function () use ($data, $authUser) {
            $requestData = [
                'requestNumber' => $data['requestNumber'] ?? null,
                'requestTypeId' => $data['requestTypeId'],
                'userId' => $authUser->id,
                'customerId' => $data['customerId'] ?? null,
                'requestDate' => $data['requestDate'] ?? null,
                'currency' => $data['currency'] ?? null,
                'area' => $data['area'] ?? null,
                'reasonId' => $data['reasonId'] ?? null,
                'classificationId' => $data['classificationId'] ?? null,
                'deliveryNote' => $data['deliveryNote'] ?? null,
                'invoiceNumber' => $data['invoiceNumber'] ?? null,
                'invoiceDate' => $data['invoiceDate'] ?? null,
                'exchangeRate' => $data['exchangeRate'] ?? null,
                'status' => $data['status'] ?? 'created',
                'amount' => $data['amount'] ?? null,
                'hasIva' => $data['hasIva'] ?? ($data['iva'] ?? null),
                'totalAmount' => $data['totalAmount'] ?? null,
                'comments' => $data['comments'] ?? null,
            ];

            $createdRequest = null;

            if (!empty($data['requestNumber'])) {
                $draft = RequestModel::query()
                    ->where('userId', $authUser->id)
                    ->where('status', 'draft')
                    ->where('requestNumber', $data['requestNumber'])
                    ->lockForUpdate()
                    ->first();

                if ($draft) {
                    $draft->update($requestData);
                    $createdRequest = $draft;
                }
            }

            if (!$createdRequest) {
                $createdRequest = RequestModel::create($requestData);
            }

            $alreadyAssignedToWorkflow = WorkflowRequestCurrentStep::query()
                ->where('requestId', $createdRequest->id)
                ->exists();

            if (!$alreadyAssignedToWorkflow) {
                $this->requestWorkflowService->assignRequestToWorkflow($createdRequest, (int) $authUser->id);
            }

            return $createdRequest;
        });

        $this->requestWorkflowService->notifyAssignedUser($created->id);

        return $created->refresh();
    }

    public function updateRequest(int $requestId, array $data, mixed $authUser, bool $isAdmin): array
    {
        $requestModel = RequestModel::query()->find($requestId);

        if (!$requestModel) {
            return ['status' => 404, 'message' => 'Request no encontrada', 'data' => null];
        }

        if (!$isAdmin && (int) $requestModel->userId !== (int) $authUser->id) {
            return ['status' => 403, 'message' => 'No tienes permisos para editar esta solicitud', 'data' => null];
        }

        if (in_array((string) $requestModel->status, ['approved', 'rejected'], true)) {
            return ['status' => 422, 'message' => 'No se puede editar una solicitud finalizada', 'data' => null];
        }

        $updateData = $this->buildEditableRequestData($data);

        if (empty($updateData)) {
            return [
                'status' => 422,
                'message' => 'No se enviaron campos válidos para actualizar',
                'errors' => ['fields' => ['No hay campos editables en el payload']],
                'data' => null,
            ];
        }

        $wasDraft = (string) $requestModel->status === 'draft';
        $requestModel->update($updateData);

        if ($wasDraft) {
            $alreadyAssignedToWorkflow = WorkflowRequestCurrentStep::query()
                ->where('requestId', $requestModel->id)
                ->exists();

            if (!$alreadyAssignedToWorkflow) {
                $this->requestWorkflowService->assignRequestToWorkflow($requestModel, (int) $authUser->id);
                $this->requestWorkflowService->notifyAssignedUser($requestModel->id);
            }
        }

        return [
            'status' => 200,
            'message' => 'Solicitud actualizada',
            'data' => $requestModel->load([
                'requestType',
                'user',
                'reason',
                'classification',
                'workflowCurrentStep.workflowStep',
                'workflowCurrentStep.assignedRole',
                'workflowCurrentStep.assignedUser',
            ]),
        ];
    }

    public function saveDraft(array $data, mixed $authUser): array
    {
        $draftData = [
            'requestTypeId' => $data['requestTypeId'],
            'customerId' => $data['customerId'] ?? null,
            'requestNumber' => $data['requestNumber'] ?? null,
            'requestDate' => $data['requestDate'] ?? null,
            'currency' => $data['currency'] ?? null,
            'area' => $data['area'] ?? null,
            'reasonId' => $data['reasonId'] ?? null,
            'classificationId' => $data['classificationId'] ?? null,
            'deliveryNote' => $data['deliveryNote'] ?? null,
            'invoiceNumber' => $data['invoiceNumber'] ?? null,
            'invoiceDate' => $data['invoiceDate'] ?? null,
            'exchangeRate' => $data['exchangeRate'] ?? null,
            'amount' => $data['amount'] ?? null,
            'hasIva' => $data['hasIva'] ?? false,
            'totalAmount' => $data['totalAmount'] ?? null,
            'comments' => $data['comments'] ?? null,
            'creditNumber' => $data['creditNumber'] ?? null,
            'creditDebitRefId' => $data['creditDebitRefId'] ?? null,
            'newInvoice' => $data['newInvoice'] ?? null,
            'sapReturnOrder' => $data['sapReturnOrder'] ?? null,
            'hasRga' => $data['hasRga'] ?? false,
            'warehouseCode' => $data['warehouseCode'] ?? null,
            'replenishmentAmount' => $data['replenishmentAmount'] ?? null,
            'hasReplenishmentIva' => $data['hasReplenishmentIva'] ?? false,
            'replenishmentTotal' => $data['replenishmentTotal'] ?? null,
            'warehouseAmount' => $data['warehouseAmount'] ?? null,
            'hasWarehouseIva' => $data['hasWarehouseIva'] ?? false,
            'warehouseTotal' => $data['warehouseTotal'] ?? null,
            'status' => 'draft',
            'userId' => $authUser->id,
        ];

        $draftId = $data['id'] ?? null;
        $requestNumber = $data['requestNumber'] ?? null;

        if (!$draftId && !empty($requestNumber)) {
            $existingDraft = RequestModel::query()
                ->where('userId', $authUser->id)
                ->where('status', 'draft')
                ->where('requestNumber', $requestNumber)
                ->first();

            if ($existingDraft) {
                $existingDraft->update($draftData);

                return [
                    'status' => 200,
                    'message' => 'Borrador actualizado',
                    'data' => $existingDraft->load(['requestType', 'user', 'reason', 'classification']),
                ];
            }
        }

        if ($draftId) {
            $draft = RequestModel::find($draftId);
            if (!$draft) {
                return ['status' => 404, 'message' => 'Borrador no encontrado', 'data' => null];
            }

            if ((int) $draft->userId !== (int) $authUser->id) {
                return ['status' => 403, 'message' => 'No tienes permisos para actualizar este borrador', 'data' => null];
            }

            $draft->update($draftData);

            return [
                'status' => 200,
                'message' => 'Borrador actualizado',
                'data' => $draft->load(['requestType', 'user', 'reason', 'classification']),
            ];
        }

        $draft = RequestModel::create($draftData);

        return [
            'status' => 201,
            'message' => 'Borrador guardado',
            'data' => $draft->load(['requestType', 'user', 'reason', 'classification']),
        ];
    }

    public function getDrafts(int $userId, int $perPage, int $page)
    {
        return $this->buildDraftsQuery($userId)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getMyPending(mixed $authUser, bool $isAdmin, ?int $requestTypeId, string $search, int $perPage, int $page)
    {
        return $this->buildMyPendingQuery($authUser, $isAdmin, $requestTypeId, $search)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getByRequestType(int $requestTypeId, int $perPage, string $search)
    {
        return $this->buildByRequestTypeQuery($requestTypeId, $search)
            ->paginate($perPage);
    }

    public function getByCustomerId(string $customerId, int $perPage, int $page)
    {
        return RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.workflowStep',
            'workflowCurrentStep.assignedRole',
            'workflowCurrentStep.assignedUser',
        ])
            ->where('customerId', $customerId)
            ->where('requestTypeId', 6)
            ->whereDoesntHave('returnOrderRequest')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getRequestsForExport(string $module, mixed $authUser, bool $isAdmin, array $filters): Collection
    {
        $scope = mb_strtolower((string) ($filters['scope'] ?? 'page'));
        $perPageInput = $filters['per_page'] ?? $filters['perPage'] ?? 15;
        $perPage = max(1, (int) $perPageInput);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = match ($module) {
            'pending_me' => $this->buildMyPendingQuery(
                $authUser,
                $isAdmin,
                isset($filters['requestTypeId']) ? (int) $filters['requestTypeId'] : null,
                $search
            ),
            'request_type' => $this->buildByRequestTypeQuery(
                isset($filters['requestTypeId']) ? (int) $filters['requestTypeId'] : 0,
                $search
            ),
            'drafts' => $this->buildDraftsQuery((int) ($authUser->id ?? 0)),
            default => throw ValidationException::withMessages([
                'module' => ['Módulo de exportación no soportado.'],
            ]),
        };

        if ($module === 'request_type' && (int) ($filters['requestTypeId'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'requestTypeId' => ['requestTypeId es requerido para module=request_type.'],
            ]);
        }

        if ($scope === 'all') {
            return $query->get();
        }

        return $query->paginate($perPage, ['*'], 'page', $page)->getCollection();
    }

    private function buildMyPendingQuery(mixed $authUser, bool $isAdmin, ?int $requestTypeId, string $search)
    {
        $query = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.workflowStep',
            'workflowCurrentStep.assignedRole',
            'workflowCurrentStep.assignedUser',
        ])
            ->whereHas('workflowCurrentStep', function ($workflowQuery) use ($authUser, $isAdmin) {
                $workflowQuery->where('status', 'pending');

                if (!$isAdmin) {
                    $workflowQuery->where('assignedUserId', (int) $authUser->id);
                }
            })
            ->orderBy('id');

        if ($requestTypeId !== null) {
            $query->where('requestTypeId', $requestTypeId);
        }

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        return $query;
    }

    private function buildByRequestTypeQuery(int $requestTypeId, string $search)
    {
        $query = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.workflowStep',
            'workflowCurrentStep.assignedRole',
            'workflowCurrentStep.assignedUser',
        ])
            ->where('requestTypeId', $requestTypeId)
            ->orderBy('id');

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        return $query;
    }

    private function buildDraftsQuery(int $userId)
    {
        return RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.workflowStep',
            'workflowCurrentStep.assignedRole',
            'workflowCurrentStep.assignedUser',
        ])
            ->where('userId', $userId)
            ->where('status', 'draft')
            ->orderByDesc('updatedAt');
    }

    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($subQuery) use ($search) {
            $subQuery->where('requestNumber', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('customerId', 'like', "%{$search}%")
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('fullName', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('reason', function ($reasonQuery) use ($search) {
                    $reasonQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('classification', function ($classificationQuery) use ($search) {
                    $classificationQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
        });
    }

    private function buildEditableRequestData(array $data): array
    {
        $editableFields = [
            'requestNumber',
            'requestTypeId',
            'customerId',
            'requestDate',
            'currency',
            'area',
            'reasonId',
            'classificationId',
            'deliveryNote',
            'invoiceNumber',
            'invoiceDate',
            'exchangeRate',
            'orderNumber',
            'creditNumber',
            'amount',
            'hasIva',
            'totalAmount',
            'comments',
            'creditDebitRefId',
            'newInvoice',
            'sapReturnOrder',
            'hasRga',
            'warehouseCode',
            'replenishmentAmount',
            'hasReplenishmentIva',
            'replenishmentTotal',
            'warehouseAmount',
            'hasWarehouseIva',
            'warehouseTotal',
        ];

        $filtered = array_intersect_key($data, array_flip($editableFields));

        if (!array_key_exists('hasIva', $filtered) && array_key_exists('iva', $data)) {
            $filtered['hasIva'] = $data['iva'];
        }

        return $filtered;
    }
}