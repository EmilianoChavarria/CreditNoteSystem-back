<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\InvoicePdfService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePdfService $invoicePdfService,
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

    /**
     * Genera y descarga el PDF de una factura.
     * GET /invoices/{id}/pdf
     */
    public function downloadPdf(string $id)
    {
        try {
            return $this->invoicePdfService->generatePdf($id);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 404), 404);
        }
    }

    /**
     * Descarga el XML de una factura.
     * GET /invoices/{id}/xml
     */
    public function downloadXml(string $id)
    {
        try {
            return $this->invoicePdfService->downloadXml($id);
        } catch (RuntimeException $e) {
            return response()->json(ApiResponse::error($e->getMessage(), null, 404), 404);
        }
    }
}