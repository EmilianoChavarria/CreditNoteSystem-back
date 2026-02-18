<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Models\RequestReason;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
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

    public function getAllReasons()
    {
        $reasons = RequestReason::all();

        return response()->json(ApiResponse::success("Reasons", $reasons));
    }

    public function createRequest(Request $request){
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

        $created->update(['requestNumber' => $created->id]);

        return response()->json(ApiResponse::success('Request creado', $created->refresh(), 201), 201);
    }
}
