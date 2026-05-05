<?php

namespace App\Http\Requests\ReturnOrders;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnOrderInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clientId'                    => ['required', 'integer'],
            'chargeTypeId'                => ['required', 'integer', 'exists:chargeTypes,id'],
            'customRate'                  => ['nullable', 'numeric', 'min:0'],
            'notes'                       => ['nullable', 'string', 'max:1000'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.invoiceFolio'         => ['required', 'string', 'max:50'],
            'items.*.invoiceClientId'      => ['required', 'integer'],
            'items.*.conceptoIndex'        => ['required', 'integer', 'min:0'],
            'items.*.requestedQuantity'    => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'chargeTypeId.required'                  => 'El tipo de cargo es requerido.',
            'chargeTypeId.exists'                    => 'El tipo de cargo seleccionado no existe.',
            'items.required'                         => 'Debe incluir al menos un producto.',
            'items.min'                              => 'Debe incluir al menos un producto.',
            'items.*.invoiceFolio.required'           => 'El folio de la factura es requerido.',
            'items.*.invoiceClientId.required'        => 'El clientId de la factura es requerido.',
            'items.*.conceptoIndex.required'          => 'El índice del concepto es requerido.',
            'items.*.requestedQuantity.required'      => 'La cantidad a devolver es requerida.',
            'items.*.requestedQuantity.gt'            => 'La cantidad a devolver debe ser mayor a 0.',
        ];
    }
}
