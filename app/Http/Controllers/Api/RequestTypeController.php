<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RequestTypeController extends Controller
{
    public function getAll()
    {
        $requestTypes = DB::table('requesttype')->get();
        return response()->json(ApiResponse::success('Request Types', $requestTypes));
    }

    public function saveRequestType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error("Invalid data", $validator->errors(), 422));
        }

        $now = Carbon::now();

        $id = DB::table('requesttype')->insertGetId([
            'name' => $request->input('name'),
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
        
        $requestObject = DB::table('requesttype')->where('id', $id)->first();


        return response()->json(ApiResponse::success('Request type created succesfully', $requestObject, 201), 201);
    }

    public function updateRequestType(Request $request, $id)
    {
        $requestType = DB::table('requesttype')->where('id', $id)->first();

        if (!$requestType) {
            return response()->json(ApiResponse::error('Request type not found', null, 404), 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error("Invalid data", $validator->errors(), 422));
        }

        $now = Carbon::now();

        DB::table('requesttype')
            ->where('id', $id)
            ->update([
                'name' => $request->input('name'),
                'updatedAt' => $now,
            ]);
        
        $requestObject = DB::table('requesttype')->where('id', $id)->first();

        return response()->json(ApiResponse::success('Request type updated successfully', $requestObject));
    }

    public function deleteRequestType($id)
    {
        $requestType = DB::table('requesttype')->where('id', $id)->first();

        if (!$requestType) {
            return response()->json(ApiResponse::error('Request type not found', null, 404), 404);
        }

        DB::table('requesttype')->where('id', $id)->delete();

        return response()->json(ApiResponse::success('Request type deleted successfully'));
    }
}
