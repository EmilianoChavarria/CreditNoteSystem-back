<?php

namespace App\Http\Requests\ReturnOrders;

use Illuminate\Foundation\Http\FormRequest;

class LinkReturnOrderToRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returnOrderId'       => ['required', 'integer', 'exists:returnOrders,id'],
            'requestId'           => ['required', 'integer', 'exists:requests,id'],
            'returnChargePercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'returnOrderId.exists' => 'La orden de devolución no existe.',
            'requestId.exists'     => 'La solicitud no existe.',
        ];
    }
}
