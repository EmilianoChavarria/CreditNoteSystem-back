<?php

namespace App\Http\Requests\NationalCustomers;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreNationalCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Los ids suelen mandarse como números en el JSON; se normalizan a string. */
    protected function prepareForValidation(): void
    {
        $numbers = $this->input('customerNumbers');

        if (is_array($numbers)) {
            $this->merge([
                'customerNumbers' => array_map(
                    fn ($n) => is_scalar($n) ? trim((string) $n) : $n,
                    $numbers
                ),
            ]);
        }
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
