<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestClassification;
use App\Models\RequestType;
use App\Models\Workflow;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkflowController extends Controller
{
    public function getAll()
    {
        $workflows = Workflow::with(['requestType', 'classification'])->get();

        return response()->json(ApiResponse::success('Workflows', $workflows));
    }

    public function getById($id)
    {
        $workflow = Workflow::with(['requestType', 'classification'])->find($id);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Workflow', $workflow));
    }

    public function store(Request $request)
    {
        $requestTypeTable = (new RequestType())->getTable();
        $classificationTable = (new RequestClassification())->getTable();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'isActive' => ['nullable', 'boolean'],
            'requestTypeId' => ['required', 'integer', 'exists:' . $requestTypeTable . ',id'],
            'classificationId' => ['nullable', 'integer', 'exists:' . $classificationTable . ',id'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validator->errors(), 422), 422);
        }

        $workflow = Workflow::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'isActive' => $request->input('isActive', true),
            'requestTypeId' => $request->input('requestTypeId'),
            'classificationId' => $request->input('classificationId'),
        ]);

        return response()->json(
            ApiResponse::success('Workflow created successfully', $workflow->load(['requestType', 'classification']), 201),
            201
        );
    }

    public function update(Request $request, $id)
    {
        $workflow = Workflow::find($id);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        $requestTypeTable = (new RequestType())->getTable();
        $classificationTable = (new RequestClassification())->getTable();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'isActive' => ['sometimes', 'nullable', 'boolean'],
            'requestTypeId' => ['sometimes', 'required', 'integer', 'exists:' . $requestTypeTable . ',id'],
            'classificationId' => ['sometimes', 'nullable', 'integer', 'exists:' . $classificationTable . ',id'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Invalid data', $validator->errors(), 422), 422);
        }

        $workflow->update($request->only([
            'name',
            'description',
            'isActive',
            'requestTypeId',
            'classificationId',
        ]));

        return response()->json(ApiResponse::success('Workflow updated successfully', $workflow->load(['requestType', 'classification'])));
    }

    public function delete($id)
    {
        $workflow = Workflow::find($id);

        if (!$workflow) {
            return response()->json(ApiResponse::error('Workflow not found', null, 404), 404);
        }

        $workflow->delete();

        return response()->json(ApiResponse::success('Workflow deleted successfully'));
    }
}
