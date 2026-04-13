<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idClient' => ['required', 'integer', 'unique:customers,idClient'],
            'area' => ['required', 'in:sales,aftermarket'],
            'salesEngineerId' => ['required', 'integer', 'exists:users,id'],
            'salesManagerId' => ['required', 'integer', 'exists:users,id'],
            'financeManagerId' => ['required', 'integer', 'exists:users,id'],
            'marketingManagerId' => ['required', 'integer', 'exists:users,id'],
            'customerServiceManagerId' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}