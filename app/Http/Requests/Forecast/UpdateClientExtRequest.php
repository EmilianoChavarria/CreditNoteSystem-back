<?php

namespace App\Http\Requests\Forecast;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientExtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area'                     => ['sometimes', 'nullable', 'string', 'max:255'],
            'processorId'              => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'salesEngineerId'          => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'salesManagerId'           => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'financeManagerId'         => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'marketingManagerId'       => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'customerServiceManagerId' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
