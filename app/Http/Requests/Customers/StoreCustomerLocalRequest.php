<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idClient' => ['required', 'string', 'max:255'],
            'salesEngineerId' => ['required', 'integer'],
            'salesManagerId' => ['required', 'integer'],
            'financeManagerId' => ['required', 'integer'],
            'marketingManagerId' => ['required', 'integer'],
            'customerServiceManagerId' => ['required', 'integer'],
            'area' => ['required', 'string'],
        ];
    }
}