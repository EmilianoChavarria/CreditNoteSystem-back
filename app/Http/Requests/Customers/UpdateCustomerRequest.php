<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'idClient' => ['sometimes', 'required', 'integer', Rule::unique('customers', 'idClient')->ignore($id, 'idCustomer')],
            'area' => ['sometimes', 'required', 'in:sales,aftermarket'],
            'salesEngineerId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'salesManagerId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'financeManagerId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'marketingManagerId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'customerServiceManagerId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'idClient.required' => 'El idClient es requerido',
            'idClient.unique' => 'El idClient ya existe',
            'area.in' => 'El area debe ser sales o aftermarket',
            'salesEngineerId.required' => 'El ingeniero de ventas es requerido',
            'salesEngineerId.exists' => 'El ingeniero de ventas no existe',
            'salesManagerId.required' => 'El gerente de ventas es requerido',
            'salesManagerId.exists' => 'El gerente de ventas no existe',
            'financeManagerId.required' => 'El gerente de finanzas es requerido',
            'financeManagerId.exists' => 'El gerente de finanzas no existe',
            'marketingManagerId.required' => 'El gerente de marketing es requerido',
            'marketingManagerId.exists' => 'El gerente de marketing no existe',
            'customerServiceManagerId.required' => 'El gerente de servicio al cliente es requerido',
            'customerServiceManagerId.exists' => 'El gerente de servicio al cliente no existe',
        ];
    }
}