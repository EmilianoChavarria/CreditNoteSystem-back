<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDraftRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:requests,id'],
            'requestTypeId' => ['required', 'integer', 'exists:requesttype,id'],
            'customerId' => ['nullable', 'integer'],
            'requestNumber' => ['nullable', 'string', 'max:50'],
            'requestDate' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'area' => ['nullable', 'string', 'max:255'],
            'reasonId' => ['nullable', 'integer', 'exists:requestreasons,id'],
            'classificationId' => ['nullable', 'integer', 'exists:requestclassification,id'],
            'deliveryNote' => ['nullable', 'string', 'max:255'],
            'invoiceNumber' => ['nullable', 'string', 'max:50'],
            'invoiceDate' => ['nullable', 'date'],
            'exchangeRate' => ['nullable', 'numeric'],
            'amount' => ['nullable', 'numeric'],
            'hasIva' => ['nullable', 'boolean'],
            'totalAmount' => ['nullable', 'numeric'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'creditNumber' => ['nullable', 'string', 'max:50'],
            'creditDebitRefId' => ['nullable', 'string', 'max:255'],
            'newInvoice' => ['nullable', 'string', 'max:255'],
            'sapReturnOrder' => ['nullable', 'string', 'max:255'],
            'hasRga' => ['nullable', 'boolean'],
            'warehouseCode' => ['nullable', 'string', 'max:50'],
            'replenishmentAmount' => ['nullable', 'numeric'],
            'hasReplenishmentIva' => ['nullable', 'boolean'],
            'replenishmentTotal' => ['nullable', 'numeric'],
            'warehouseAmount' => ['nullable', 'numeric'],
            'hasWarehouseIva' => ['nullable', 'boolean'],
            'warehouseTotal' => ['nullable', 'numeric'],
        ];
    }
}