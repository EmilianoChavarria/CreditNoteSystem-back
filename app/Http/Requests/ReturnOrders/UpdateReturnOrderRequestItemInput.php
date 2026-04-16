<?php

namespace App\Http\Requests\ReturnOrders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnOrderRequestItemInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sapId'                          => ['nullable', 'string', 'max:50'],
            'replenishmentAccepted'           => ['nullable', 'numeric', 'min:0'],
            'replenishmentReasonForRejection' => ['nullable', 'string', 'max:255'],
            'warehouseReceived'               => ['nullable', 'numeric', 'min:0'],
            'warehouseAccepted'               => ['nullable', 'numeric', 'min:0'],
            'warehouseReasonForRejection'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'replenishmentAccepted.min'  => 'Replenishment accepted debe ser mayor o igual a 0.',
            'warehouseReceived.min'      => 'Warehouse received debe ser mayor o igual a 0.',
            'warehouseAccepted.min'      => 'Warehouse accepted debe ser mayor o igual a 0.',
        ];
    }
}
