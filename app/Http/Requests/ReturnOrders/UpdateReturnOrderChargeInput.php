<?php

namespace App\Http\Requests\ReturnOrders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnOrderChargeInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chargeTypeId' => ['nullable', 'integer', 'exists:chargeTypes,id'],
            'customRate'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'chargeTypeId.exists' => 'El tipo de cargo seleccionado no existe.',
            'customRate.min'      => 'El porcentaje personalizado no puede ser negativo.',
        ];
    }
}
