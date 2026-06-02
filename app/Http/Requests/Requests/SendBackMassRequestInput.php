<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBackMassRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestIds'           => ['required', 'array', 'min:1'],
            'requestIds.*'         => ['integer', 'distinct'],
            'targetWorkflowStepId' => ['required', 'integer', 'min:1'],
            'comments'             => ['nullable', 'string', 'max:7000'],
        ];
    }
}
