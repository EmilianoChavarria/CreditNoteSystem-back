<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBackRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'targetWorkflowStepId' => ['required', 'integer', 'min:1'],
            'comments'             => ['nullable', 'string', 'max:7000'],
        ];
    }
}
