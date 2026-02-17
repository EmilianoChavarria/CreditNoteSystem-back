<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    public function getAllReasons()
    {
        $reasons = DB::table('requestreasons')->get();

        return response()->json(ApiResponse::success("Reasons", $reasons));
    }

    public function createRequest(Request $request){
        
        // $validator = Validator::make($request->all(), [
        //     'moduleName' => ['required', 'string', 'max:150', 'unique:modules,moduleName'],
        // ]);

        // if ($validator->fails()) {
        //     return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        // }

        $id = DB::table('requests')->insertGetId([
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

        DB::table('requests')->where('id', $id)->update(['requestNumber' => $id]);

        $request = DB::table('requests')->where('id', $id)->first();

        return response()->json(ApiResponse::success('Request creado', $request, 201), 201);
    }


}
