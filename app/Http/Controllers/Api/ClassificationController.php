<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequestClassificationResource;
use App\Models\RequestClassification;
use App\Models\RequestType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassificationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'unique:requestClassification,name', 'string', 'max:255'],
            'requestType' => ['required', 'array'],
            'requestType.*' => ['required', 'int', 'exists:requesttype,id']
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $classification = RequestClassification::create([
            'name' => $request->input('name'),
        ]);

        // Asociar tipos de solicitud
        $typeIds = $request->input('requestType');
        $classification->requestTypes()->sync($typeIds);

        return response()->json(ApiResponse::success('Clasificación creada exitosamente', RequestClassificationResource::make($classification->load('requestTypes')), 201), 201);
    }

    public function getClassificationGrouped()
    {
        $classification = RequestClassification::select('type')
            ->groupBy('type')
            ->get();

        return response()->json(ApiResponse::success('Classifications', RequestClassificationResource::collection($classification), 201), 201);
    }

    public function getAllByRequestTypeId($typeRequestId)
    {
        $requestType = RequestType::find($typeRequestId);

        if (!$requestType) {
            return response()->json(ApiResponse::error('Tipo de solicitud no encontrado', null, 404), 404);
        }

        $classifications = $requestType->classifications()->get();

        if ($classifications->isEmpty()) {
            return response()->json(ApiResponse::success('No hay clasificaciones para este tipo de solicitud', []), 200);
        }

        return response()->json(ApiResponse::success('Clasificaciones', RequestClassificationResource::collection($classifications)));
    }
}
