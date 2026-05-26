<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelMassRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestIds' => ['required', 'array', 'min:1'],
            'requestIds.*' => ['integer', 'distinct'],
            'comments' => ['nullable', 'string', 'max:7000'],
        ];
    }
}
