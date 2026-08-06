<?php

namespace App\Http\Requests\NationalCustomers;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreNationalCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerNumbers'   => ['required', 'array', 'min:1'],
            'customerNumbers.*' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'customerNumbers.required' => 'Debe enviar al menos un número de cliente.',
            'customerNumbers.array'    => 'customerNumbers debe ser un arreglo de números de cliente.',
        ];
    }
}
