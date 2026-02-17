<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClassificationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['string', 'max:10'],
            'name' => ['required', 'unique:requestClassification,name', 'string', 'max:255'],
            'requestType' => ['required', 'array'],
            'requestType.*' => ['required', 'int', 'exists:requesttype,id']
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $now = Carbon::now();

        // Insertar en requestClassification
        $classificationId = DB::table('requestClassification')->insertGetId([
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        // Insertar en classificationtypes
        $typeIds = $request->input('requestType');
        $data = [];
        foreach ($typeIds as $typeId) {
            $data[] = [
                'classificationId' => $classificationId,
                'typeRequestId' => $typeId
            ];
        }
        DB::table('classificationtypes')->insert($data);

        $classification = DB::table('requestClassification')
            ->where('id', $classificationId)
            ->first();

        return response()->json(ApiResponse::success('Clasificación creada exitosamente', $classification, 201), 201);
    }

    public function getAllByRequestTypeId($typeRequestId)
    {
        $classifications = DB::table('requestClassification')
            ->join('classificationtypes', 'requestClassification.id', '=', 'classificationtypes.classificationId')
            ->where('classificationtypes.typeRequestId', $typeRequestId)
            ->select(
                'requestClassification.id',
                'requestClassification.name'
            )
            ->distinct()
            ->get();

        if ($classifications->isEmpty()) {
            return response()->json(ApiResponse::success('No hay clasificaciones para este tipo de solicitud', []), 200);
        }

        return response()->json(ApiResponse::success('Clasificaciones', $classifications));
    }
}
