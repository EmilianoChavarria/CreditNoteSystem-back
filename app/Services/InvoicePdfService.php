<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;

class InvoicePdfService
{
    private const CFDI_NS  = 'http://www.sat.gob.mx/cfd/4';
    private const TFD_NS   = 'http://www.sat.gob.mx/TimbreFiscalDigital';
    private const ITTEC_NS = 'http://www.ittec.com.mx/schemas';

    public function generatePdf(string $invoiceId): mixed
    {
        $invoice = DB::table('comprobantes_tme700618rc7')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) {
            throw new RuntimeException("Factura {$invoiceId} no encontrada.");
        }

        $xmlData = $this->parseXml((string) $invoice->folio, (string) $invoice->receptorId);
        $data    = $this->buildViewData($invoice, $xmlData);

        $pdf = Pdf::loadView('invoices.timken-invoice', $data);
        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream("factura-{$invoiceId}.pdf");
    }

    private function parseXml(string $folio, string $clientId): array
    {
        $path = "{$folio}-{$clientId}.xml";

        if (!Storage::disk('public')->exists($path)) {
            throw new RuntimeException("XML no encontrado: {$path}");
        }

        $content = Storage::disk('public')->get($path);
        $xml = simplexml_load_string((string) $content);

        if ($xml === false) {
            throw new RuntimeException("No se pudo parsear el XML de la factura {$folio}.");
        }

        $xml->registerXPathNamespace('cfdi',  self::CFDI_NS);
        $xml->registerXPathNamespace('tfd',   self::TFD_NS);
        $xml->registerXPathNamespace('ittec', self::ITTEC_NS);

        $rootAttrs = $this->xmlAttrs($xml);

        // Emisor / Receptor
        $emisorNodes   = $xml->xpath('//cfdi:Emisor');
        $receptorNodes = $xml->xpath('//cfdi:Receptor');
        $emisor   = $emisorNodes   ? $this->xmlAttrs($emisorNodes[0])   : [];
        $receptor = $receptorNodes ? $this->xmlAttrs($receptorNodes[0]) : [];

        // Conceptos
        $conceptoNodes = $xml->xpath('//cfdi:Concepto');
        $conceptos     = [];

        foreach ($conceptoNodes as $node) {
            $a      = $this->xmlAttrs($node);
            $parsed = $this->parseDescripcion((string) ($a['Descripcion'] ?? ''));

            $traslados = [];
            foreach ($node->xpath('cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado') as $t) {
                $traslados[] = $this->xmlAttrs($t);
            }

            $pedimentos = [];
            foreach ($node->xpath('cfdi:InformacionAduanera') as $p) {
                $pedimentos[] = (string) $this->xmlAttrs($p)['NumeroPedimento'];
            }

            $conceptos[] = [
                'claveProdServ' => (string) ($a['ClaveProdServ']  ?? ''),
                'cantidad'      => (float)  ($a['Cantidad']       ?? 0),
                'claveUnidad'   => (string) ($a['ClaveUnidad']    ?? ''),
                'unidad'        => (string) ($a['Unidad']         ?? ''),
                'valorUnitario' => (float)  ($a['ValorUnitario']  ?? 0),
                'importe'       => (float)  ($a['Importe']        ?? 0),
                'partNumber'    => $parsed['partNumber'],
                'description'   => $parsed['description'],
                'noPedido'      => $parsed['noPedido'],
                'remision'      => $parsed['remision'],
                'custPart'      => $parsed['custPart'],
                'orig'          => $parsed['orig'],
                'traslados'     => $traslados,
                'pedimentos'    => $pedimentos,
            ];
        }

        // Global IVA
        $totalIva = 0.0;
        $impNodes = $xml->xpath('//cfdi:Impuestos[@TotalImpuestosTrasladados]');
        if ($impNodes) {
            $totalIva = (float) $this->xmlAttrs($impNodes[0])['TotalImpuestosTrasladados'];
        }

        // TimbreFiscalDigital
        $tfdNodes = $xml->xpath('//tfd:TimbreFiscalDigital');
        $tfd = $tfdNodes ? $this->xmlAttrs($tfdNodes[0]) : [];

        // Addenda ittec:Timken
        $addenda      = [];
        $emisorAddr   = [];
        $receptorAddr = [];
        $entregaAddr  = [];

        $timkenNodes = $xml->xpath('//ittec:Timken');
        if ($timkenNodes) {
            $timken  = $timkenNodes[0];
            $addenda = $this->xmlAttrs($timken);

            $emisorAddrNodes = $xml->xpath('//ittec:Timken/ittec:Emisor');
            if ($emisorAddrNodes) {
                $emisorAddr = $this->xmlAttrs($emisorAddrNodes[0]);
            }

            $receptorAddrNodes = $xml->xpath('//ittec:Timken/ittec:Receptor');
            if ($receptorAddrNodes) {
                $receptorAddr = $this->xmlAttrs($receptorAddrNodes[0]);
            }

            $entregaNodes = $xml->xpath('//ittec:Timken/ittec:Entrega');
            if ($entregaNodes) {
                $entregaAddr = $this->xmlAttrs($entregaNodes[0]);
            }
        }

        return [
            'folio'           => (string) ($rootAttrs['Folio']              ?? ''),
            'fecha'           => (string) ($rootAttrs['Fecha']              ?? ''),
            'formaPago'       => (string) ($rootAttrs['FormaPago']          ?? ''),
            'noCertificado'   => (string) ($rootAttrs['NoCertificado']      ?? ''),
            'condiciones'     => (string) ($rootAttrs['CondicionesDePago']  ?? ''),
            'moneda'          => (string) ($rootAttrs['Moneda']             ?? ''),
            'tipoCambio'      => (string) ($rootAttrs['TipoCambio']         ?? ''),
            'subTotal'        => (float)  ($rootAttrs['SubTotal']           ?? 0),
            'total'           => (float)  ($rootAttrs['Total']              ?? 0),
            'sello'           => (string) ($rootAttrs['Sello']              ?? ''),
            'lugarExpedicion' => (string) ($rootAttrs['LugarExpedicion']    ?? ''),
            'metodoPago'      => (string) ($rootAttrs['MetodoPago']         ?? ''),
            'emisor'          => $emisor,
            'receptor'        => $receptor,
            'conceptos'       => $conceptos,
            'totalIva'        => $totalIva,
            'tfd'             => $tfd,
            'addenda'         => $addenda,
            'emisorAddr'      => $emisorAddr,
            'receptorAddr'    => $receptorAddr,
            'entregaAddr'     => $entregaAddr,
        ];
    }

    private function parseDescripcion(string $descripcion): array
    {
        // Format: ^PARTNUMBER;DESCRIPTION^NOPEDIDO^REMISION^CANTBO^CUSTPART^ORIG^pedimentos
        $parts = explode('^', $descripcion);

        $rawPart   = $parts[1] ?? '';
        $partSplit = explode(';', $rawPart, 2);

        return [
            'partNumber'  => trim((string) ($partSplit[0] ?? '')),
            'description' => trim((string) ($partSplit[1] ?? '')),
            'noPedido'    => trim((string) ($parts[2]     ?? '')),
            'remision'    => trim((string) ($parts[3]     ?? '')),
            'custPart'    => trim((string) ($parts[5]     ?? '')),
            'orig'        => trim((string) ($parts[6]     ?? '')),
        ];
    }

    private function buildViewData(object $invoice, array $xmlData): array
    {
        $obs3Parts = [];
        if (!empty($xmlData['addenda']['obs3'])) {
            $obs3Parts = array_values(array_filter(
                explode('^', (string) $xmlData['addenda']['obs3']),
                fn ($p) => $p !== ''
            ));
        }

        $tfd            = $xmlData['tfd'];
        $cadenaOriginal = '';
        if (!empty($tfd['UUID'])) {
            $cadenaOriginal = sprintf(
                '||%s|%s|%s|%s|%s|%s||',
                $tfd['Version']          ?? '1.1',
                $tfd['UUID']             ?? '',
                $tfd['FechaTimbrado']    ?? '',
                $tfd['RfcProvCertif']    ?? '',
                $tfd['SelloCFD']         ?? '',
                $tfd['NoCertificadoSAT'] ?? ''
            );
        }

        return [
            'invoice'        => $invoice,
            'xml'            => $xmlData,
            'obs3Parts'      => $obs3Parts,
            'cadenaOriginal' => $cadenaOriginal,
        ];
    }

    private function xmlAttrs(SimpleXMLElement $node): array
    {
        $result = [];
        foreach ($node->attributes() as $key => $val) {
            $result[(string) $key] = (string) $val;
        }
        return $result;
    }
}
