<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\DB;

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
            'customerId' => ['nullable', 'string'],
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
            'comments' => ['nullable', 'string', 'max:65535'],
            'replenishmentAmount' => ['sometimes', 'nullable', 'numeric'],
            'hasReplenishmentIva' => ['sometimes', 'nullable', 'boolean'],
            'replenishmentTotal' => ['sometimes', 'nullable', 'numeric'],
            'warehouseAmount' => ['sometimes', 'nullable', 'numeric'],
            'hasWarehouseIva' => ['sometimes', 'nullable', 'boolean'],
            'warehouseTotal' => ['sometimes', 'nullable', 'numeric'],
            'uploadSupport'   => ['nullable', 'array'],
            'uploadSupport.*' => ['file'],
            'sapScreen'       => ['nullable', 'array'],
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