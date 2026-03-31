<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Request as RequestModel;
use App\Models\RequestClassification;
use App\Models\RequestReason;
use App\Models\Workflow;
use App\Models\WorkflowRequestCurrentStep;
use App\Models\WorkflowRequestHistory;
use App\Models\WorkflowRequestStep;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepTransition;
use App\Services\RequestHistoryService;
use App\Services\RequestNumberService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    public function __construct(
        private readonly RequestNumberService $requestNumberService,
        private readonly RequestHistoryService $requestHistoryService
    )
    {
    }

    public function getRequestHistoryById(int $requestId)
    {
        try {
            $history = $this->requestHistoryService->getHistoryByRequestId($requestId);

            return response()->json(ApiResponse::success('Request history', $history));
        } catch (ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('Request no encontrada', null, 404), 404);
        }
    }

    public function getPendingByRole(Request $request, int $id)
    {
        $authUser = $request->attributes->get('authUser');

        $requests = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.assignedRole',
        ])
            ->whereHas('workflowCurrentStep', function ($query) use ($authUser) {
                $query->where('assignedRoleId', $authUser->roleId)
                    ->where('status', 'pending');
            })
            // ->where('status', 'created')
            ->where('requestTypeId', $id)
            ->orderBy('id')
            ->get();
        // var_dump($requests);
        return response()->json(ApiResponse::success('Pending requests for your role', $requests));
    }

    public function getAll()
    {
        $requests = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification'
        ])->orderBy('id')->get();

        return response()->json(ApiResponse::success('Requests', $requests));
    }

    public function getAllByRequestType(Request $request, int $id)
    {
        $perPage = $request->query('per_page', 15);
        $requests = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.assignedRole',
        ])->orderBy('id')
            ->where('requestTypeId', $id)
            ->paginate($perPage);

        return response()->json(ApiResponse::success('Requests', $requests));
    }

    public function getAllReasons()
    {
        $reasons = RequestReason::all();

        return response()->json(ApiResponse::success("Reasons", $reasons));
    }

    public function getNextRequestNumber(int $requestTypeId)
    {
        if ($requestTypeId <= 0) {
            return response()->json(
                ApiResponse::error('requestTypeId inválido', ['requestTypeId' => ['Debe ser un número entero positivo']], 422),
                422
            );
        }

        $requestNumber = $this->requestNumberService->generateRequestNumber($requestTypeId);

        return response()->json(ApiResponse::success('Next request number', [
            'requestTypeId' => $requestTypeId,
            'requestNumber' => $requestNumber,
            'prefix' => $this->requestNumberService->getPrefixForType($requestTypeId),
        ], 201), 201);
    }

    public function createRequest(Request $request)
    {
        $user = $request->attributes->get('authUser');
        $created = DB::transaction(function () use ($request, $user) {
            $requestData = [
                'requestNumber' => $request->input('requestNumber'),
                'requestTypeId' => $request->input('requestTypeId'),
                'userId' => $user->id,
                'customerId' => $request->input('customerId'),
                'requestDate' => $request->input('requestDate'),
                'currency' => $request->input('currency'),
                'area' => $request->input('area'),
                'reasonId' => $request->input('reasonId'),
                'classificationId' => $request->input('classificationId'),
                'deliveryNote' => $request->input('deliveryNote'),
                'invoiceNumber' => $request->input('invoiceNumber'),
                'invoiceDate' => $request->input('invoiceDate'),
                'exchangeRate' => $request->input('exchangeRate'),
                'status' => $request->input('status', 'created'),
                'amount' => $request->input('amount'),
                'hasIva' => $request->input('hasIva', $request->input('iva')),
                'totalAmount' => $request->input('totalAmount'),
                'comments' => $request->input('comments'),
            ];

            $createdRequest = null;

            if ($request->filled('requestNumber')) {
                $draft = RequestModel::query()
                    ->where('userId', $user->id)
                    ->where('status', 'draft')
                    ->where('requestNumber', $request->input('requestNumber'))
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
                $this->assignRequestToWorkflow($createdRequest, (int) $user->id);
            }

            return $createdRequest;
        });


        return response()->json(ApiResponse::success('Request creado', $created->refresh(), 201), 201);
    }

    public function approve(Request $request, int $requestId)
    {
        $validation = Validator::make($request->all(), [
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validation->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validation->errors(), 422), 422);
        }

        $authUser = $request->attributes->get('authUser');

        try {
            $result = DB::transaction(function () use ($requestId, $authUser, $request) {
                $requestModel = RequestModel::query()->lockForUpdate()->find($requestId);

                if (!$requestModel) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'payload' => ApiResponse::error('Request no encontrada', null, 404),
                    ];
                }

                $currentStep = WorkflowRequestCurrentStep::query()
                    ->where('requestId', $requestId)
                    ->lockForUpdate()
                    ->first();

                if (!$currentStep) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'payload' => ApiResponse::error('La solicitud no tiene un paso actual asignado', null, 422),
                    ];
                }

                if ((int) $authUser->roleId !== (int) $currentStep->assignedRoleId) {
                    return [
                        'ok' => false,
                        'status' => 403,
                        'payload' => ApiResponse::error('No tienes permisos para aprobar esta solicitud en el paso actual', null, 403),
                    ];
                }

                $activeRequestStep = WorkflowRequestStep::query()
                    ->where('requestId', $requestId)
                    ->where('workflowStepId', $currentStep->workflowStepId)
                    ->where('status', 'pending')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$activeRequestStep) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'payload' => ApiResponse::error('No existe un paso pendiente para aprobar', null, 422),
                    ];
                }

                $currentWorkflowStep = WorkflowStep::query()->find($currentStep->workflowStepId);

                if (!$currentWorkflowStep) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'payload' => ApiResponse::error('El paso actual del workflow no existe', null, 422),
                    ];
                }

                $activeRequestStep->update([
                    'status' => 'approved',
                    'completedAt' => now(),
                ]);

                WorkflowRequestHistory::create([
                    'requestWorkflowStepId' => $activeRequestStep->id,
                    'requestId' => $requestId,
                    'workflowStepId' => $currentWorkflowStep->id,
                    'actionUserId' => (int) $authUser->id,
                    'actionType' => 'approved',
                    'comments' => $request->input('comments'),
                ]);

                $nextStep = $this->resolveNextStep($requestModel, $currentWorkflowStep);

                if (!$nextStep || (bool) $currentWorkflowStep->isFinalStep) {
                    $currentStep->update([
                        'status' => 'approved',
                    ]);

                    $requestModel->update([
                        'status' => 'approved',
                    ]);

                    return [
                        'ok' => true,
                        'status' => 200,
                        'payload' => ApiResponse::success('Solicitud aprobada y flujo finalizado', $requestModel->refresh()),
                    ];
                }

                $nextRequestStep = WorkflowRequestStep::create([
                    'requestId' => $requestId,
                    'workflowStepId' => $nextStep->id,
                    'assignedRoleId' => $nextStep->roleId,
                    'status' => 'pending',
                    'startedAt' => now(),
                ]);

                WorkflowRequestCurrentStep::updateOrCreate(
                    ['requestId' => $requestId],
                    [
                        'workflowId' => $currentStep->workflowId,
                        'workflowStepId' => $nextStep->id,
                        'assignedRoleId' => $nextStep->roleId,
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

                $requestModel->update([
                    'status' => 'pending',
                ]);

                return [
                    'ok' => true,
                    'status' => 200,
                    'payload' => ApiResponse::success('Solicitud aprobada y enviada al siguiente paso', $requestModel->refresh()),
                ];
            });

            return response()->json($result['payload'], $result['status']);
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Invalid data', $e->errors(), 422), 422);
        }
    }

    public function reject(Request $request, int $requestId)
    {
        $validation = Validator::make($request->all(), [
            'comments' => ['required', 'string', 'max:1000'],
        ]);

        if ($validation->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validation->errors(), 422), 422);
        }

        $authUser = $request->attributes->get('authUser');

        $result = DB::transaction(function () use ($requestId, $authUser, $request) {
            $requestModel = RequestModel::query()->lockForUpdate()->find($requestId);

            if (!$requestModel) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'payload' => ApiResponse::error('Request no encontrada', null, 404),
                ];
            }

            $currentStep = WorkflowRequestCurrentStep::query()
                ->where('requestId', $requestId)
                ->lockForUpdate()
                ->first();

            if (!$currentStep) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'payload' => ApiResponse::error('La solicitud no tiene un paso actual asignado', null, 422),
                ];
            }

            if ((int) $authUser->roleId !== (int) $currentStep->assignedRoleId) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'payload' => ApiResponse::error('No tienes permisos para rechazar esta solicitud en el paso actual', null, 403),
                ];
            }

            $activeRequestStep = WorkflowRequestStep::query()
                ->where('requestId', $requestId)
                ->where('workflowStepId', $currentStep->workflowStepId)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$activeRequestStep) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'payload' => ApiResponse::error('No existe un paso pendiente para rechazar', null, 422),
                ];
            }

            $currentWorkflowStep = WorkflowStep::query()->find($currentStep->workflowStepId);

            if (!$currentWorkflowStep) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'payload' => ApiResponse::error('El paso actual del workflow no existe', null, 422),
                ];
            }

            $activeRequestStep->update([
                'status' => 'rejected',
                'completedAt' => now(),
            ]);

            WorkflowRequestHistory::create([
                'requestWorkflowStepId' => $activeRequestStep->id,
                'requestId' => $requestId,
                'workflowStepId' => $currentStep->workflowStepId,
                'actionUserId' => (int) $authUser->id,
                'actionType' => 'rejected',
                'comments' => $request->input('comments'),
            ]);

            $previousStep = $this->resolvePreviousStepByOrder($currentWorkflowStep);

            if (!$previousStep) {
                $currentStep->update([
                    'status' => 'rejected',
                ]);

                $requestModel->update([
                    'status' => 'rejected',
                ]);

                return [
                    'ok' => true,
                    'status' => 200,
                    'payload' => ApiResponse::success('Solicitud rechazada. No existe paso anterior para regresar', $requestModel->refresh()),
                ];
            }

            $previousRequestStep = WorkflowRequestStep::create([
                'requestId' => $requestId,
                'workflowStepId' => $previousStep->id,
                'assignedRoleId' => $previousStep->roleId,
                'status' => 'pending',
                'startedAt' => now(),
            ]);

            WorkflowRequestCurrentStep::updateOrCreate(
                ['requestId' => $requestId],
                [
                    'workflowId' => $currentStep->workflowId,
                    'workflowStepId' => $previousStep->id,
                    'assignedRoleId' => $previousStep->roleId,
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

            $requestModel->update([
                'status' => 'pending',
            ]);

            return [
                'ok' => true,
                'status' => 200,
                'payload' => ApiResponse::success('Solicitud rechazada y regresada al paso anterior', $requestModel->refresh()),
            ];
        });

        return response()->json($result['payload'], $result['status']);
    }

    private function assignRequestToWorkflow(RequestModel $requestModel, int $actionUserId): void
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

        $requestStep = WorkflowRequestStep::create([
            'requestId' => $requestModel->id,
            'workflowStepId' => $initialStep->id,
            'assignedRoleId' => $initialStep->roleId,
            'status' => 'pending',
            'startedAt' => now(),
        ]);

        WorkflowRequestCurrentStep::updateOrCreate(
            ['requestId' => $requestModel->id],
            [
                'workflowId' => $workflow->id,
                'workflowStepId' => $initialStep->id,
                'assignedRoleId' => $initialStep->roleId,
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

    public function saveDraft(Request $request)
    {
        $user = $request->attributes->get('authUser');

        $validation = Validator::make($request->all(), [
            'id' => ['nullable', 'integer', 'exists:requests,id'],
            'requestTypeId' => ['required', 'integer', 'exists:requesttype,id'],
            'customerId' => ['nullable', 'integer'],
            'requestNumber' => ['nullable', 'string', 'max:50'],
            'requestDate' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'area' => ['nullable', 'string', 'max:255'],
            'reasonId' => ['nullable', 'integer', 'exists:requestreasons,id'],
            'classificationId' => ['nullable', 'integer', 'exists:requestclassification,id'],
            'deliveryNote' => ['nullable', 'string', 'max:255'],
            'invoiceNumber' => ['nullable', 'string', 'max:50'],
            'invoiceDate' => ['nullable', 'date'],
            'exchangeRate' => ['nullable', 'numeric'],
            'amount' => ['nullable', 'numeric'],
            'hasIva' => ['nullable', 'boolean'],
            'totalAmount' => ['nullable', 'numeric'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'creditNumber' => ['nullable', 'string', 'max:50'],
            'creditDebitRefId' => ['nullable', 'string', 'max:255'],
            'newInvoice' => ['nullable', 'string', 'max:255'],
            'sapReturnOrder' => ['nullable', 'string', 'max:255'],
            'hasRga' => ['nullable', 'boolean'],
            'warehouseCode' => ['nullable', 'string', 'max:50'],
            'replenishmentAmount' => ['nullable', 'numeric'],
            'hasReplenishmentIva' => ['nullable', 'boolean'],
            'replenishmentTotal' => ['nullable', 'numeric'],
            'warehouseAmount' => ['nullable', 'numeric'],
            'hasWarehouseIva' => ['nullable', 'boolean'],
            'warehouseTotal' => ['nullable', 'numeric'],
        ]);

        if ($validation->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validation->errors(), 422), 422);
        }

        try {
            $draftData = [
                'requestTypeId' => $request->input('requestTypeId'),
                'customerId' => $request->input('customerId'),
                'requestNumber' => $request->input('requestNumber'),
                'requestDate' => $request->input('requestDate'),
                'currency' => $request->input('currency'),
                'area' => $request->input('area'),
                'reasonId' => $request->input('reasonId'),
                'classificationId' => $request->input('classificationId'),
                'deliveryNote' => $request->input('deliveryNote'),
                'invoiceNumber' => $request->input('invoiceNumber'),
                'invoiceDate' => $request->input('invoiceDate'),
                'exchangeRate' => $request->input('exchangeRate'),
                'amount' => $request->input('amount'),
                'hasIva' => $request->input('hasIva', false),
                'totalAmount' => $request->input('totalAmount'),
                'comments' => $request->input('comments'),
                'creditNumber' => $request->input('creditNumber'),
                'creditDebitRefId' => $request->input('creditDebitRefId'),
                'newInvoice' => $request->input('newInvoice'),
                'sapReturnOrder' => $request->input('sapReturnOrder'),
                'hasRga' => $request->input('hasRga', false),
                'warehouseCode' => $request->input('warehouseCode'),
                'replenishmentAmount' => $request->input('replenishmentAmount'),
                'hasReplenishmentIva' => $request->input('hasReplenishmentIva', false),
                'replenishmentTotal' => $request->input('replenishmentTotal'),
                'warehouseAmount' => $request->input('warehouseAmount'),
                'hasWarehouseIva' => $request->input('hasWarehouseIva', false),
                'warehouseTotal' => $request->input('warehouseTotal'),
                'status' => 'draft',
                'userId' => $user->id,
            ];

            $draftId = $request->input('id');
            $requestNumber = $request->input('requestNumber');

            if (!$draftId && $requestNumber) {
                $existingDraft = RequestModel::query()
                    ->where('userId', $user->id)
                    ->where('status', 'draft')
                    ->where('requestNumber', $requestNumber)
                    ->first();

                if ($existingDraft) {
                    $existingDraft->update($draftData);
                    $result = $existingDraft->load(['requestType', 'user', 'reason', 'classification']);

                    return response()->json(ApiResponse::success('Borrador actualizado', $result, 200), 200);
                }
            }

            if ($draftId) {
                // Actualizar borrador existente
                $draft = RequestModel::find($draftId);

                if (!$draft) {
                    return response()->json(ApiResponse::error('Borrador no encontrado', null, 404), 404);
                }

                // Verificar que el borrador pertenezca al usuario
                if ($draft->userId !== $user->id) {
                    return response()->json(ApiResponse::error('No tienes permisos para actualizar este borrador', null, 403), 403);
                }

                $draft->update($draftData);
                $result = $draft->load(['requestType', 'user', 'reason', 'classification']);

                return response()->json(ApiResponse::success('Borrador actualizado', $result, 200), 200);
            } else {
                // Crear nuevo borrador
                $draft = RequestModel::create($draftData);
                $result = $draft->load(['requestType', 'user', 'reason', 'classification']);

                return response()->json(ApiResponse::success('Borrador guardado', $result, 201), 201);
            }
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al guardar el borrador', ['error' => $e->getMessage()], 500), 500);
        }
    }

    public function getDrafts(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = $request->query('perPage', 15);
        $page = $request->query('page', 1);

        try {
            $drafts = RequestModel::with([
                'requestType',
                'user',
                'reason',
                'classification'
            ])
                ->where('userId', (int) $authUser->id)
                ->where('status', 'draft')
                ->orderByDesc('updatedAt')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(ApiResponse::success('Borradores', $drafts));
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al obtener borradores', ['error' => $e->getMessage()], 500), 500);
        }
    }
}
