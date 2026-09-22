<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerReturnsEmailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails'   => ['present', 'array'],
            'emails.*' => ['required', 'email'],
        ];
    }
}
