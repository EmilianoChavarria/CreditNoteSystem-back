<?php

namespace App\Http\Requests\NationalCustomers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNationalCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails'           => ['sometimes', 'required', 'string'],
            'returnPercentage' => ['sometimes', 'required', 'numeric', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required'           => 'Los correos electrónicos son requeridos.',
            'returnPercentage.required' => 'El porcentaje de retorno es requerido.',
            'returnPercentage.between'  => 'El porcentaje de retorno debe estar entre 0 y 100.',
        ];
    }
}
