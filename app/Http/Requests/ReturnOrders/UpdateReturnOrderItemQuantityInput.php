<?php

namespace App\Http\Requests\ReturnOrders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnOrderItemQuantityInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestedQuantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'requestedQuantity.required' => 'La cantidad a devolver es requerida.',
            'requestedQuantity.gt'       => 'La cantidad a devolver debe ser mayor a 0.',
        ];
    }
}
