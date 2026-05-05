<?php

namespace App\Http\Requests\Workflows;

use App\Models\RequestClassification;
use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requestTypeTable = (new RequestType())->getTable();
        $classificationTable = (new RequestClassification())->getTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'isActive' => ['nullable', 'boolean'],
            'requestTypeId' => ['required', 'integer', 'exists:' . $requestTypeTable . ',id'],
            'classificationType' => ['nullable', 'string', 'exists:' . $classificationTable . ',type'],
        ];
    }
}