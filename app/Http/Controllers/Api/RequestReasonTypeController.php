<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RequestReasonTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequestReasonTypeController extends Controller
{
    public function __construct(private RequestReasonTypeService $service) {}

    public function syncReasons(Request $request, int $requestTypeId)
    {
        $validator = Validator::make($request->all(), [
            'reasonIds'   => ['required', 'array'],
            'reasonIds.*' => ['required', 'integer', 'exists:requestreasons,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validator->errors(), 422), 422);
        }

        $requestType = $this->service->syncReasons($requestTypeId, $request->input('reasonIds'));

        return response()->json(ApiResponse::success('Reasons assigned successfully', $requestType));
    }
}
