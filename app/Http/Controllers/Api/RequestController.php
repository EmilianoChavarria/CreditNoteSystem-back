<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Request as RequestModel;
use App\Models\RequestClassification;
use App\Models\RequestCustomer;
use App\Models\RequestReason;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRequestCurrentStep;
use App\Models\WorkflowRequestHistory;
use App\Models\WorkflowRequestStep;
use App\Models\WorkflowStep;
use App\Services\RequestNumberService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    public function __construct(private readonly RequestNumberService $requestNumberService)
    {
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
        $perPage = $request->query('perPage');
        $requests = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification'
        ])->orderBy('id')
            ->where('requestTypeId', $id)
            ->cursorPaginate($perPage);

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
            $createdRequest = RequestModel::create([
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
                'status' => $request->input('status'),
                'amount' => $request->input('amount'),
                'hasIva' => $request->input('iva'),
                'totalAmount' => $request->input('totalAmount'),
                'comments' => $request->input('comments'),
            ]);

            

            $this->assignRequestToWorkflow($createdRequest, (int) $user->id);

            return $createdRequest;
        });


        return response()->json(ApiResponse::success('Request creado', $created->refresh(), 201), 201);
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

        $assignedUser = User::query()
            ->where('roleId', $initialStep->roleId)
            ->where('isActive', true)
            ->orderBy('id')
            ->first();

        $assignedTo = (int) ($assignedUser?->id ?? $actionUserId);

        $requestStep = WorkflowRequestStep::create([
            'requestId' => $requestModel->id,
            'workflowStepId' => $initialStep->id,
            'assignedTo' => $assignedTo,
            'status' => 'pending',
            'startedAt' => now(),
        ]);

        WorkflowRequestCurrentStep::updateOrCreate(
            ['requestId' => $requestModel->id],
            [
                'workflowId' => $workflow->id,
                'workflowStepId' => $initialStep->id,
                'assignedTo' => $assignedTo,
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

    private function resolveCustomer(mixed $customerInput): ?Customer
    {
        if ($customerInput === null || $customerInput === '') {
            return null;
        }

        if (is_numeric($customerInput)) {
            $number = (int) $customerInput;

            $customerById = Customer::find($number);
            if ($customerById) {
                return $customerById;
            }

            $customerByNumber = Customer::where('customerNumber', $number)->first();
            if ($customerByNumber) {
                return $customerByNumber;
            }
        }

        $customerByNumber = Customer::where('customerNumber', (string) $customerInput)->first();
        if ($customerByNumber) {
            return $customerByNumber;
        }

        return Customer::where('customerName', (string) $customerInput)->first();
    }
}
