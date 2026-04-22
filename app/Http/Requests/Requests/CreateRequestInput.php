<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestNumber' => ['nullable', 'string', 'max:50'],
            'requestTypeId' => ['required', 'integer', 'exists:requesttype,id'],
            'customerId' => ['nullable', 'integer'],
            'requestDate' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'area' => ['nullable', 'string', 'max:255'],
            'reasonId' => ['nullable', 'integer', 'exists:requestreasons,id'],
            'classificationId' => ['nullable', 'integer', 'exists:requestclassification,id'],
            'deliveryNote' => ['nullable', 'string', 'max:255'],
            'invoiceNumber' => ['nullable', 'string', 'max:50'],
            'invoiceDate' => ['nullable', 'date'],
            'newInvoice' => ['nullable', 'date'], // sólo para re-invoicing
            'warehouseCode' => ['nullable', 'string'], // sólo para material-return
            'exchangeRate' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:20'],
            'amount' => ['nullable', 'numeric'],
            'hasIva' => ['nullable', 'boolean'],
            'iva' => ['nullable'],
            'totalAmount' => ['nullable', 'numeric'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'replenishmentAmount' => ['sometimes', 'nullable', 'numeric'],
            'hasReplenishmentIva' => ['sometimes', 'nullable', 'boolean'],
            'replenishmentTotal' => ['sometimes', 'nullable', 'numeric'],
            'warehouseAmount' => ['sometimes', 'nullable', 'numeric'],
            'hasWarehouseIva' => ['sometimes', 'nullable', 'boolean'],
            'warehouseTotal' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}