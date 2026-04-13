<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectMassRequestInput extends FormRequest
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
            'comments' => ['required', 'string', 'max:1000'],
        ];
    }
}