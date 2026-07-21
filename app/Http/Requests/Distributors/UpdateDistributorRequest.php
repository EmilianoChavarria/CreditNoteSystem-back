<?php

namespace App\Http\Requests\Distributors;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistributorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'businessName' => ['sometimes', 'required', 'string', 'max:255'],
            'taxId'        => ['sometimes', 'required', 'string', 'max:20'],
            'countrycode'  => ['sometimes', 'required', 'string', 'max:5'],
            'address'      => ['sometimes', 'required', 'string'],
            'emails'       => ['sometimes', 'required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'businessName.required' => 'La razón social es requerida.',
            'taxId.required'        => 'El RFC es requerido.',
            'address.required'      => 'El domicilio es requerido.',
            'emails.required'       => 'Los correos electrónicos son requeridos.',
            'countrycode.required'  => 'El código del país es requeridos.',
        ];
    }
}
