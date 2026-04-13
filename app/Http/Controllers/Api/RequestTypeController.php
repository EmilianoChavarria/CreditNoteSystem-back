<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequestTypeResource;
use App\Models\RequestType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequestTypeController extends Controller
{
    public function getAll()
    {
        $requestTypes = RequestType::all();
        return response()->json(ApiResponse::success('Request Types', RequestTypeResource::collection($requestTypes)));
    }

    public function saveRequestType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error("Invalid data", $validator->errors(), 422));
        }

        $requestType = RequestType::create([
            'name' => $request->input('name'),
        ]);

        return response()->json(ApiResponse::success('Request type created succesfully', RequestTypeResource::make($requestType), 201), 201);
    }

    public function updateRequestType(Request $request, $id)
    {
        $requestType = RequestType::find($id);

        if (!$requestType) {
            return response()->json(ApiResponse::error('Request type not found', null, 404), 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error("Invalid data", $validator->errors(), 422));
        }

        $requestType->update([
            'name' => $request->input('name'),
        ]);

        return response()->json(ApiResponse::success('Request type updated successfully', RequestTypeResource::make($requestType)));
    }

    public function deleteRequestType($id)
    {
        $requestType = RequestType::find($id);

        if (!$requestType) {
            return response()->json(ApiResponse::error('Request type not found', null, 404), 404);
        }

        $requestType->delete();

        return response()->json(ApiResponse::success('Request type deleted successfully'));
    }
}
