<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class UpdateRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requestNumber' => ['sometimes', 'nullable', 'string', 'max:50'],
            'requestTypeId' => ['sometimes', 'integer', 'exists:requesttype,id'],
            'customerId' => ['sometimes', 'nullable', 'string'],
            'requestDate' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reasonId' => ['sometimes', 'nullable', 'integer', 'exists:requestreasons,id'],
            'classificationId' => ['sometimes', 'nullable', 'integer', 'exists:requestclassification,id'],
            'deliveryNote' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invoiceNumber' => ['sometimes', 'nullable', 'string', 'max:50'],
            'invoiceDate' => ['sometimes', 'nullable', 'date'],
            'exchangeRate' => ['sometimes', 'nullable', 'numeric'],
            'amount' => ['sometimes', 'nullable', 'numeric'],
            'hasIva' => ['sometimes', 'nullable', 'boolean'],
            'iva' => ['sometimes', 'nullable'],
            'totalAmount' => ['sometimes', 'nullable', 'numeric'],
            'comments' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'orderNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'creditNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'creditDebitRefId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'newInvoice' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sapReturnOrder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hasRga' => ['sometimes', 'nullable', 'boolean'],
            'warehouseCode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'replenishmentAmount' => ['sometimes', 'nullable', 'numeric'],
            'hasReplenishmentIva' => ['sometimes', 'nullable', 'boolean'],
            'replenishmentTotal' => ['sometimes', 'nullable', 'numeric'],
            'warehouseAmount' => ['sometimes', 'nullable', 'numeric'],
            'hasWarehouseIva' => ['sometimes', 'nullable', 'boolean'],
            'warehouseTotal' => ['sometimes', 'nullable', 'numeric'],
            'uploadSupport'   => ['sometimes', 'nullable', 'array'],
            'uploadSupport.*' => ['file'],
            'sapScreen'       => ['sometimes', 'nullable', 'array'],
            'sapScreen.*'     => ['file'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function () {
            $comments = $this->input('comments');

            if (!$comments || !str_contains($comments, ':')) {
                return;
            }

            $parts      = explode(':', $comments);
            $foliosPart = trim(end($parts));

            if (empty($foliosPart)) {
                return;
            }

            $folios = array_values(array_filter(array_map('trim', explode(',', $foliosPart))));

            if (empty($folios)) {
                return;
            }

            $valid = DB::connection('invoices')
                ->table('pfaccfdi_cfdipr.comprobantes_TME700618RC7')
                ->whereIn('folio', $folios)
                ->where('status', 'Emitido')
                ->pluck('folio')
                ->map(fn ($f) => (string) $f)
                ->toArray();

            $invalid = array_values(array_filter($folios, fn ($f) => !\in_array($f, $valid)));

            if (!empty($invalid)) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Facturas inválidas o sin status "Emitido"',
                        'errors'  => ['comments' => $invalid],
                    ], 422)
                );
            }
        });
    }
}
