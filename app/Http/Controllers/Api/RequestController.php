<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Request as RequestModel;
use App\Models\RequestCustomer;
use App\Models\RequestReason;
use App\Services\RequestNumberService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
            'requestCustomers',
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
            'requestCustomers',
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

            $customerInput = $request->input('idCustomer', $request->input('customerId'));
            $resolvedCustomer = $this->resolveCustomer($customerInput);

            if ($customerInput !== null || $resolvedCustomer !== null) {
                RequestCustomer::create([
                    'idRequest' => $createdRequest->id,
                    'idCustomer' => (string) ($request->input('idCustomer')
                        ?? ($resolvedCustomer?->customerNumber)
                        ?? $customerInput),
                    'salesEngineerId' => (int) ($request->input('salesEngineerId') ?? $resolvedCustomer?->salesEngineerId),
                    'salesManagerId' => (int) ($request->input('salesManagerId') ?? $resolvedCustomer?->salesManagerId),
                    'financeManagerId' => (int) ($request->input('financeManagerId') ?? $resolvedCustomer?->financeManagerId),
                    'marketingManagerId' => (int) ($request->input('marketingManagerId') ?? $resolvedCustomer?->marketingManagerId),
                    'customerServiceManagerId' => (int) ($request->input('customerServiceManagerId') ?? $resolvedCustomer?->customerServiceManagerId),
                ]);
            }

            return $createdRequest;
        });


        return response()->json(ApiResponse::success('Request creado', $created->refresh(), 201), 201);
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
