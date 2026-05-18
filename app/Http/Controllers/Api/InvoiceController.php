<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\FesaWsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly FesaWsService $fesaWsService,
    ) {
    }

    public function search(Request $request, int $clientId)
    {
        $perPage = max(1, (int) $request->query('per_page', $request->query('perPage', 15)));
        $page = max(1, (int) $request->query('page', 1));
        $invoices = $this->invoiceService->searchInvoices($clientId, $request->only([
            'uuid',
            'folio',
            'receptorRfc',
            'receptorNombre',
            'moneda',
            'fechaInicial',
            'fechaFinal',
        ]), $perPage, $page);

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

    public function getInvoicesByClientIdAndChargeType(Request $request, int $clientId, string $chargeType)
    {
        $perPage = max(1, (int) $request->query('per_page', $request->query('perPage', 15)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $invoices = $this->invoiceService->getInvoicesByClientIdAndChargeType($clientId, $chargeType, $perPage, $page, $search);

        return response()->json(ApiResponse::success('Facturas', $invoices));
    }

    private function buildTestXml(string $fecha): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<cfdi:Comprobante xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:cfdi="http://www.sat.gob.mx/cfd/4"'
            . ' xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"'
            . ' xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd'
            . ' http://www.sat.gob.mx/TimbreFiscalDigital http://www.sat.gob.mx/sitio_internet/cfd/TimbreFiscalDigital/TimbreFiscalDigitalv11.xsd"'
            . ' Version="4.0" Folio="544610" Fecha="' . $fecha . '"'
            . ' Sello="" FormaPago="99" NoCertificado="" Certificado=""'
            . ' CondicionesDePago="Pay on the 25th of each month" Moneda="USD" TipoCambio="17.3587"'
            . ' TipoDeComprobante="I" MetodoPago="PPD" LugarExpedicion="54715" Exportacion="02"'
            . ' SubTotal="0.00" Total="0.00">'
            . '<cfdi:Emisor Rfc="TME700618RC7" Nombre="TIMKEN DE MEXICO" RegimenFiscal="601"/>'
            . '<cfdi:Receptor Rfc="XEXX010101000" Nombre="THE TIMKEN CORPORATION" ResidenciaFiscal="USA"'
            . ' NumRegIdTrib="341878497" UsoCFDI="S01" DomicilioFiscalReceptor="54715" RegimenFiscalReceptor="616"/>'
            . '<cfdi:Conceptos>'
            . '<cfdi:Concepto ClaveProdServ="31171516" NoIdentificacion="68712-20024" Cantidad="7.000"'
            . ' ClaveUnidad="H87" Unidad="PC" Descripcion="TEST" ValorUnitario="27.255714" Importe="190.79" ObjetoImp="02">'
            . '<cfdi:Impuestos><cfdi:Traslados>'
            . '<cfdi:Traslado Base="190.79" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.000000" Importe="0.00"/>'
            . '</cfdi:Traslados></cfdi:Impuestos>'
            . '</cfdi:Concepto>'
            . '</cfdi:Conceptos>'
            . '<cfdi:Impuestos TotalImpuestosTrasladados="0.00">'
            . '<cfdi:Traslados>'
            . '<cfdi:Traslado Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.000000" Importe="0.00" Base="190.79"/>'
            . '</cfdi:Traslados>'
            . '</cfdi:Impuestos>'
            . '<cfdi:Complemento><tfd:TimbreFiscalDigital/></cfdi:Complemento>'
            . '</cfdi:Comprobante>';
    }

    /**
     * GET /invoices/fesa/test/pdf?idTransaccion=545092
     */
    public function testFesaPdf(Request $request)
    {
        $idTransaccion = $request->query('idTransaccion', '545092');
        $result = $this->fesaWsService->emitirRaw($idTransaccion, $this->buildTestXml(now()->format('Y-m-d') . 'T00:00:00'));

        if (!empty($result['fault']) || !empty($result['error'])) {
            return response()->json($result, 500);
        }

        $pdfB64 = $result['response']['pdf'] ?? null;

        if (!$pdfB64) {
            return response()->json(['error' => 'WS no retornó campo pdf', 'response' => $result['response']], 500);
        }

        return response(base64_decode($pdfB64), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"fesa-{$idTransaccion}.pdf\"",
        ]);
    }

    /**
     * GET /invoices/fesa/test/xml?idTransaccion=545092
     */
    public function testFesaXml(Request $request)
    {
        $idTransaccion = $request->query('idTransaccion', '545092');
        $result = $this->fesaWsService->emitirRaw($idTransaccion, $this->buildTestXml(now()->format('Y-m-d') . 'T00:00:00'));

        if (!empty($result['fault']) || !empty($result['error'])) {
            return response()->json($result, 500);
        }

        $xmlB64 = $result['response']['xml'] ?? null;

        if (!$xmlB64) {
            return response()->json(['error' => 'WS no retornó campo xml', 'response' => $result['response']], 500);
        }

        return response(base64_decode($xmlB64), 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"fesa-{$idTransaccion}.xml\"",
        ]);
    }
}
