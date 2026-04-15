<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Support\ApiResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
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
}