<?php

namespace App\Http\Requests\ReturnOrders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnOrderRequestItemsInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                                   => ['required', 'array', 'min:1'],
            'items.*.id'                              => ['required', 'integer'],
            'items.*.sapId'                           => ['nullable', 'string', 'max:50'],
            'items.*.replenishmentAccepted'           => ['nullable', 'numeric', 'min:0'],
            'items.*.replenishmentReasonForRejection' => ['nullable', 'string', 'max:255'],
            'items.*.warehouseReceived'               => ['nullable', 'numeric', 'min:0'],
            'items.*.warehouseAccepted'               => ['nullable', 'numeric', 'min:0'],
            'items.*.warehouseReasonForRejection'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                       => 'Se requiere al menos un item.',
            'items.*.id.required'                  => 'Cada item debe incluir su id.',
            'items.*.replenishmentAccepted.min'    => 'Replenishment accepted debe ser mayor o igual a 0.',
            'items.*.warehouseReceived.min'        => 'Warehouse received debe ser mayor o igual a 0.',
            'items.*.warehouseAccepted.min'        => 'Warehouse accepted debe ser mayor o igual a 0.',
        ];
    }
}
