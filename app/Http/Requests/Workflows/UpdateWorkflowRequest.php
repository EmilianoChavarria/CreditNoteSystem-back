<?php

namespace App\Http\Requests\Workflows;

use App\Models\RequestClassification;
use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'isActive' => ['sometimes', 'nullable', 'boolean'],
            'requestTypeId' => ['sometimes', 'required', 'integer', 'exists:' . $requestTypeTable . ',id'],
            'classificationType' => ['sometimes', 'nullable', 'string', 'exists:' . $classificationTable . ',type'],
        ];
    }
}