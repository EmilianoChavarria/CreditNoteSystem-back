<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
    }

    public function search(Request $request, int $clientId)
    {
        $invoices = $this->invoiceService->searchInvoices($clientId, $request->only([
            'uuid',
            'folio',
            'receptorRfc',
            'receptorNombre',
            'moneda',
            'fechaInicial',
            'fechaFinal',
        ]));

        return response()->json(ApiResponse::success('Facturas', $invoices));
    }

    public function getAll()
    {
        $invoices = $this->invoiceService->getAll();

        return response()->json(ApiResponse::success('Facturas', $invoices));
    }

    public function getInvoicesByClientId(int $clientId)
    {
        $invoices = $this->invoiceService->getInvoicesByClientId($clientId);

        return response()->json(ApiResponse::success('Facturas', $invoices));
    }

    public function getInvoicesByClientIdAndChargeType(int $clientId, string $chargeType)
    {
        $invoices = $this->invoiceService->getInvoicesByClientIdAndChargeType($clientId, $chargeType);

        return response()->json(ApiResponse::success('Facturas', $invoices));
    }
}