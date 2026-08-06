<?php

namespace App\Http\Requests\NationalCustomers;

use App\Models\NationalCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNationalCustomerRequest extends FormRequest
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
            'customerNumber'   => ['required', 'string', 'max:50'],
            'emails'           => ['nullable', 'string', 'max:500'],
            'returnPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'currency'         => ['nullable', Rule::in(NationalCustomer::CURRENCIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'customerNumber.required'  => 'El número de cliente es requerido.',
            'returnPercentage.between' => 'El porcentaje de retorno debe estar entre 0 y 100.',
            'currency.in'              => 'La moneda debe ser USD o MXN.',
        ];
    }
}
