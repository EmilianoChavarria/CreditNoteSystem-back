<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use RuntimeException;

class OrderNumbersBatchHandler extends AbstractBatchHandler
{
    public function __construct(private readonly BulkFileParser $fileParser)
    {
    }

    public function batchType(): string
    {
        return 'orderNumbers';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para orderNumbers.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    public function process(array $row, Batch $batch): ?int
    {
        $data = $this->validateRow([
            'requestNumber' => $this->value($row, ['request_number', 'requestnumber', 'request']),
            'orderNumber' => $this->value($row, ['order_number', 'ordernumber', 'order']),
        ], [
            'requestNumber' => ['required', 'string', 'max:255'],
            'orderNumber' => ['required', 'string', 'max:255'],
        ]);

        $requestNumber = trim((string) $data['requestNumber']);
        $request = RequestModel::where('requestNumber', $requestNumber)->first();

        if (!$request && is_numeric($requestNumber)) {
            $request = RequestModel::where('requestNumber', (int) $requestNumber)->first();
        }

        if (!$request) {
            throw new RuntimeException('Request no encontrada para requestNumber=' . $requestNumber);
        }

        if ($request->status !== 'approved') {
            throw new RuntimeException(
                "La solicitud {$requestNumber} no puede recibir un número de orden porque aún no ha sido aprobada."
            );
        }

        if ($request->attachments()->doesntExist()) {
            throw new RuntimeException(
                "La solicitud {$requestNumber} no puede recibir un número de orden porque no cuenta con documentos adjuntos."
            );
        }

        $orderNumber = trim((string) $data['orderNumber']);

        if (!empty($request->orderNumber)) {
            throw new RuntimeException(
                'El Número de Orden SAP ya ha sido asignado a esta solicitud (requestNumber=' . $requestNumber . ').'
            );
        }

        $conflict = RequestModel::where('orderNumber', $orderNumber)
            ->where('id', '!=', $request->id)
            ->first();

        if ($conflict) {
            throw new RuntimeException(
                'El Número de Orden SAP ' . $orderNumber . ' ya ha sido asignado a otra solicitud (requestNumber=' . $conflict->requestNumber . ').'
            );
        }

        $request->update([
            'orderNumber' => $orderNumber,
        ]);

        return (int) $request->id;
    }
}
