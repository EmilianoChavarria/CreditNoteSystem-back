<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class SearchCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.required' => 'El parámetro de búsqueda es requerido',
            'search.min' => 'El parámetro de búsqueda debe tener al menos 1 carácter',
        ];
    }
}