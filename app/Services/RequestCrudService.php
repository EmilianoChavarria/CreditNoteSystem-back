<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\WorkflowRequestCurrentStep;
use Illuminate\Support\Facades\DB;

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
        return RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
        ])
            ->where('userId', $userId)
            ->where('status', 'draft')
            ->orderByDesc('updatedAt')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getMyPending(mixed $authUser, bool $isAdmin, ?int $requestTypeId, string $search, int $perPage, int $page)
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

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getByRequestType(int $requestTypeId, int $perPage, string $search)
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
            ->when($search !== '', function ($query) use ($search) {
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
            })
            ->orderBy('id');

        return $query->paginate($perPage);
    }
}