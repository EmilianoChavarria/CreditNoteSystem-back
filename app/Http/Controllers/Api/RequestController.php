<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Models\RequestReason;
use App\Services\RequestNumberService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
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
            'customer',
            'reason',
            'classification'
        ])->orderBy('id')->get();

        return response()->json(ApiResponse::success('Requests', $requests));
    }

    public function getAllByRequestType(int $id)
    {
        $requests = RequestModel::with([
            'requestType',
            'user',
            'customer',
            'reason',
            'classification'
        ])->orderBy('id')
            ->where('requestTypeId', $id)
            ->get();

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
        $created = RequestModel::create([
            'requestTypeId' => $request->input('requestTypeId'),
            'userId' => $request->input('userId'),
            'requestDate' => $request->input('requestDate'),
            'currency' => $request->input('currency'),
            'customerId' => $request->input('customerId'),
            'area' => $request->input('area'),
            'reasonId' => $request->input('reasonId'),
            'classificationId' => $request->input('classificationId'),
            'deliveryNote' => $request->input('deliveryNote'),
            'invoiceNumber' => $request->input('invoiceNumber'),
            'invoiceDate' => $request->input('invoiceDate'),
            'exchangeRate' => $request->input('exchangeRate'),
            'status' => $request->input('status'),
            'creditNumber' => $request->input('creditNumber'),
            'amount' => $request->input('amount'),
            'hasIva' => $request->input('iva'),
            'totalAmount' => $request->input('totalAmount'),
        ]);

        // Generar requestNumber basado en el tipo de solicitud
        $requestNumber = $this->requestNumberService->generateRequestNumber((int) $created->requestTypeId);
        $created->update(['requestNumber' => $requestNumber]);

        return response()->json(ApiResponse::success('Request creado', $created->refresh(), 201), 201);
    }
}
