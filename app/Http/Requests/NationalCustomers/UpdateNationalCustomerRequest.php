<?php

namespace App\Http\Requests\NationalCustomers;

use App\Models\NationalCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNationalCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }
    }

    public function rules(): array
    {
        return [
            'emails'           => ['sometimes', 'required', 'string'],
            'returnPercentage' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'currency'         => ['sometimes', 'required', Rule::in(NationalCustomer::CURRENCIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required'           => 'Los correos electrónicos son requeridos.',
            'returnPercentage.required' => 'El porcentaje de retorno es requerido.',
            'returnPercentage.between'  => 'El porcentaje de retorno debe estar entre 0 y 100.',
            'currency.required'         => 'La moneda es requerida.',
            'currency.in'               => 'La moneda debe ser USD o MXN.',
        ];
    }
}
