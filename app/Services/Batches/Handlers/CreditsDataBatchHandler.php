<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use RuntimeException;

class CreditsDataBatchHandler extends AbstractBatchHandler
{
    public function __construct(private readonly BulkFileParser $fileParser)
    {
    }

    public function batchType(): string
    {
        return 'creditsData';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para creditsData.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    public function process(array $row, Batch $batch): ?int
    {
        $data = $this->validateRow([
            'requestNumber' => $this->value($row, ['request_number', 'requestnumber', 'request']),
            'creditNumber' => $this->value($row, ['credit_number', 'creditnumber', 'credit']),
        ], [
            'requestNumber' => ['required', 'string', 'max:255'],
            'creditNumber' => ['required', 'string', 'max:255'],
        ]);

        $requestNumber = trim((string) $data['requestNumber']);
        $request = RequestModel::where('requestNumber', $requestNumber)->first();

        if (!$request && is_numeric($requestNumber)) {
            $request = RequestModel::where('requestNumber', (int) $requestNumber)->first();
        }

        if (!$request) {
            throw new RuntimeException('Request no encontrada para requestNumber=' . $requestNumber);
        }

        $request->update([
            'creditNumber' => (string) $data['creditNumber'],
        ]);

        return (int) $request->id;
    }
}
