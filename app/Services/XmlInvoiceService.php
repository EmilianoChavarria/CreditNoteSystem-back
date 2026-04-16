<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class XmlInvoiceService
{
    private const CFDI_NS = 'http://www.sat.gob.mx/cfd/4';

    /**
     * Retorna los conceptos del XML de una factura desde storage/app/public.
     * El archivo sigue el patrón {folio}-{clientId}.xml
     *
     * Cada concepto incluye:
     *   - conceptoIndex   : posición 0-based en el XML
     *   - claveProdServ
     *   - cantidad        : cantidad original en la factura
     *   - claveUnidad
     *   - unidad
     *   - descripcion
     *   - valorUnitario
     *   - importe
     *   - returnedQuantity: total ya devuelto en órdenes anteriores
     *   - availableQuantity: cantidad disponible para devolver
     */
    public function getConceptos(string $invoiceFolio, int $clientId, array $returnedByIndex = []): array
    {
        $path = "{$invoiceFolio}-{$clientId}.xml";

        if (!Storage::disk('public')->exists($path)) {
            throw new RuntimeException("XML no encontrado para la factura {$invoiceFolio} del cliente {$clientId}.");
        }

        $content = Storage::disk('public')->get($path);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            throw new RuntimeException("No se pudo parsear el XML de la factura {$invoiceFolio}.");
        }

        $xml->registerXPathNamespace('cfdi', self::CFDI_NS);
        $nodes = $xml->xpath('//cfdi:Concepto');

        $conceptos = [];

        foreach ($nodes as $index => $node) {
            $attrs           = $node->attributes();
            $cantidad        = (float) $attrs->Cantidad;
            $returnedQty     = (float) ($returnedByIndex[$index] ?? 0);
            $availableQty    = max(0, $cantidad - $returnedQty);

            $conceptos[] = [
                'conceptoIndex'     => $index,
                'claveProdServ'     => (string) $attrs->ClaveProdServ,
                'cantidad'          => $cantidad,
                'claveUnidad'       => (string) $attrs->ClaveUnidad,
                'unidad'            => (string) $attrs->Unidad,
                'descripcion'       => (string) $attrs->Descripcion,
                'valorUnitario'     => (float) $attrs->ValorUnitario,
                'importe'           => (float) $attrs->Importe,
                'returnedQuantity'  => $returnedQty,
                'availableQuantity' => $availableQty,
            ];
        }

        return $conceptos;
    }

    /**
     * Retorna los atributos de un concepto específico por su índice.
     */
    public function getConceptoByIndex(string $invoiceFolio, int $clientId, int $conceptoIndex): ?array
    {
        $conceptos = $this->getConceptos($invoiceFolio, $clientId);

        return $conceptos[$conceptoIndex] ?? null;
    }
}
