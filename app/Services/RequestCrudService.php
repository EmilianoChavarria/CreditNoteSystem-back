<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\RequestClassification;
use App\Models\WorkflowRequestCurrentStep;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                'newInvoice' => $data['newInvoice'] ?? null,
                'warehouseCode' => $data['warehouseCode'] ?? null,
                'replenishmentAmount' => $data['replenishmentAmount'] ?? null,
                'hasReplenishmentIva' => $data['hasReplenishmentIva'] ?? false,
                'replenishmentTotal' => $data['replenishmentTotal'] ?? null,
                'warehouseAmount' => $data['warehouseAmount'] ?? null,
                'hasWarehouseIva' => $data['hasWarehouseIva'] ?? false,
                'warehouseTotal' => $data['warehouseTotal'] ?? null,
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

        $isCreator = (int) $requestModel->userId === (int) $authUser->id;
        $isRoleAssigned = WorkflowRequestCurrentStep::query()
            ->where('requestId', $requestId)
            ->where('status', 'pending')
            ->where('assignedRoleId', (int) $authUser->roleId)
            ->whereHas('assignedRole', fn ($q) => $q->whereIn('roleName', ['REPLENISHMENT', 'WAREHOUSE', 'IT']))
            ->exists();

        if (!$isAdmin && !$isCreator && !$isRoleAssigned) {
            return ['status' => 403, 'message' => 'No tienes permisos para editar esta solicitud', 'data' => null];
        }

        if (in_array((string) $requestModel->status, ['released', 'rejected', 'cancelled'], true)) {
            return ['status' => 422, 'message' => 'No se puede editar una solicitud finalizada', 'data' => null];
        }

        if (
            in_array((string) $requestModel->status, ['created', 'pending'], true) &&
            isset($data['status']) && (string) $data['status'] === 'draft'
        ) {
            return ['status' => 422, 'message' => 'No se puede regresar a borrador una solicitud que ya fue enviada', 'data' => null];
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

        if ($wasDraft) {
            $updateData['status'] = 'pending';
        }

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
            $existingRequest = RequestModel::query()
                ->where('userId', $authUser->id)
                ->where('requestNumber', $requestNumber)
                ->first();

            if ($existingRequest) {
                if ((string) $existingRequest->status !== 'draft') {
                    return ['status' => 422, 'message' => 'No se puede guardar como borrador una solicitud que ya fue enviada', 'data' => null];
                }

                $existingRequest->update($draftData);

                return [
                    'status' => 200,
                    'message' => 'Borrador actualizado',
                    'data' => $existingRequest->load(['requestType', 'user', 'reason', 'classification']),
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

            if ((string) $draft->status !== 'draft') {
                return ['status' => 422, 'message' => 'No se puede guardar como borrador una solicitud que ya fue enviada', 'data' => null];
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

    public function getDrafts(int $userId, string $search, int $perPage, int $page)
    {
        return $this->buildDraftsQuery($userId, $search)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function deleteDraft(int $draftId, mixed $authUser): array
    {
        $draft = RequestModel::query()
            ->whereNull('deletedAt')
            ->find($draftId);

        if (!$draft) {
            return ['status' => 404, 'message' => 'Borrador no encontrado'];
        }

        if ((string) $draft->status !== 'draft') {
            return ['status' => 422, 'message' => 'Solo se pueden eliminar solicitudes en estado borrador'];
        }

        if ((int) $draft->userId !== (int) $authUser->id) {
            return ['status' => 403, 'message' => 'No tienes permisos para eliminar este borrador'];
        }

        $draft->update([
            'deletedAt' => now(),
            'deletedBy' => (int) $authUser->id,
        ]);

        return ['status' => 200, 'message' => 'Borrador eliminado'];
    }

    public function getMyPending(mixed $authUser, bool $isAdmin, ?int $requestTypeId, string $search, int $perPage, int $page, string $roleName = '', ?int $requesterId = null, ?string $classificationType = null)
    {
        $paginator = $this->buildMyPendingQuery($authUser, $isAdmin, $requestTypeId, $search, $roleName, $requesterId, $classificationType)
            ->paginate($perPage, ['*'], 'page', $page);
        $this->enrichWithRazonSocial($paginator);

        return $paginator;
    }

    public function getMyPendingAll(mixed $authUser, bool $isAdmin, ?int $requestTypeId, string $search, string $roleName = '', ?int $requesterId = null, ?string $classificationType = null)
    {
        $results = $this->buildMyPendingQuery($authUser, $isAdmin, $requestTypeId, $search, $roleName, $requesterId, $classificationType)
            ->get();
        $this->enrichWithRazonSocial($results);

        return $results;
    }

    public function getClassificationsForMyPending(mixed $authUser, bool $isAdmin, ?int $requestTypeId): Collection
    {
        $classificationIds = $this->buildMyPendingQuery($authUser, $isAdmin, $requestTypeId, '')
            ->whereNotNull('classificationId')
            ->reorder()
            ->distinct()
            ->pluck('classificationId');

        return RequestClassification::whereIn('id', $classificationIds)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function getByRequestType(int $requestTypeId, int $perPage, string $search, string $roleName = '', ?int $requesterId = null)
    {
        $paginator = $this->buildByRequestTypeQuery($requestTypeId, $search, $roleName, $requesterId)
            ->paginate($perPage);
        $this->enrichWithRazonSocial($paginator);

        return $paginator;
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
        $roleName = trim((string) ($filters['roleName'] ?? $filters['role_name'] ?? ''));

        $query = match ($module) {
            'pending_me' => $this->buildMyPendingQuery(
                $authUser,
                $isAdmin,
                isset($filters['requestTypeId']) ? (int) $filters['requestTypeId'] : null,
                $search,
                $roleName
            ),
            'request_type' => $this->buildByRequestTypeQuery(
                isset($filters['requestTypeId']) ? (int) $filters['requestTypeId'] : 0,
                $search,
                $roleName
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

    private function buildMyPendingQuery(mixed $authUser, bool $isAdmin, ?int $requestTypeId, string $search, string $roleName = '', ?int $requesterId = null, ?string $classificationType = null)
    {
        $shouldFilterByRole = $roleName !== '' && strtolower($roleName) !== 'all';

        $query = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.workflowStep',
            'workflowCurrentStep.assignedRole',
            'workflowCurrentStep.assignedUser',
            'attachments',
        ])
            ->whereHas('workflowCurrentStep', function ($workflowQuery) use ($authUser, $isAdmin, $shouldFilterByRole, $roleName) {
                $workflowQuery->where('status', 'pending');

                if (!$isAdmin) {
                    $workflowQuery->where(function ($q) use ($authUser) {
                        $q->where('assignedUserId', (int) $authUser->id)
                            ->orWhere(function ($roleQ) use ($authUser) {
                                $roleQ->where('assignedRoleId', (int) $authUser->roleId)
                                    ->whereHas('assignedRole', function ($r) {
                                        $r->whereIn('roleName', ['REPLENISHMENT', 'WAREHOUSE', 'IT']);
                                    });
                            });
                    });
                }

                if ($shouldFilterByRole) {
                    $workflowQuery->whereHas('assignedRole', function ($roleQuery) use ($roleName) {
                        $roleQuery->where('roleName', $roleName);
                    });
                }
            })
            ->orderByDesc('createdAt');

        if ($requestTypeId !== null) {
            $query->where('requestTypeId', $requestTypeId);
        }

        if ($requesterId !== null) {
            $query->where('userId', $requesterId);
        }

        if ($classificationType !== null && $classificationType !== '') {
            $query->whereHas('classification', function ($q) use ($classificationType) {
                $q->where('type', $classificationType);
            });
        }

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        return $query;
    }

    private function buildByRequestTypeQuery(int $requestTypeId, string $search, string $roleName = '', ?int $requesterId = null)
    {
        $shouldFilterByRole = $roleName !== '' && strtolower($roleName) !== 'all';

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
            ->where('status', '!=', 'draft')
            ->when($shouldFilterByRole, function ($query) use ($roleName) {
                $query->whereHas('workflowCurrentStep.assignedRole', function ($roleQuery) use ($roleName) {
                    $roleQuery->where('roleName', $roleName);
                });
            })
            ->when($requesterId !== null, fn ($q) => $q->where('userId', $requesterId))
            ->orderByDesc('createdAt');

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        return $query;
    }

    private function buildDraftsQuery(int $userId, string $search = '')
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
            ->where('userId', $userId)
            ->where('status', 'draft')
            ->whereNull('deletedAt')
            ->orderByDesc('updatedAt');

        if ($search !== '') {
            $this->applySearchFilter($query, $search);
        }

        return $query;
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
                ->orWhereHas('workflowCurrentStep.assignedUser', function ($assignedUserQuery) use ($search) {
                    $assignedUserQuery->where('fullName', 'like', "%{$search}%")
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

    private function enrichWithRazonSocial(LengthAwarePaginator|\Illuminate\Support\Collection $source): void
    {
        try {
            if (!Schema::connection('invoices')->hasTable('clientes_TME700618RC7')) {
                return;
            }

            $collection = $source instanceof LengthAwarePaginator ? $source->getCollection() : $source;

            $customerIds = $collection
                ->pluck('customerId')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($customerIds)) {
                return;
            }

            $map = DB::connection('invoices')
                ->table('clientes_TME700618RC7')
                ->whereIn('idCliente', $customerIds)
                ->pluck('razonSocial', 'idCliente');

            $collection->each(function ($request) use ($map) {
                $request->razonSocial = $map[(string) $request->customerId] ?? null;
            });
        } catch (\Throwable) {
            // invoices DB unavailable — requests still returned without razonSocial
        }
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
