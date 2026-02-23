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

    public function process(array $row, Batch $batch): void
    {
        $data = $this->validateRow([
            'requestNumber' => $this->value($row, ['requestnumber', 'request_number']),
            'creditNumber' => $this->value($row, ['creditnumber', 'credit_number']),
        ], [
            'requestNumber' => ['required', 'integer'],
            'creditNumber' => ['required', 'string', 'max:255'],
        ]);

        $request = RequestModel::where('requestNumber', (int) $data['requestNumber'])->first();

        if (!$request) {
            throw new RuntimeException('Request no encontrada para requestNumber=' . $data['requestNumber']);
        }

        $request->update([
            'creditNumber' => (string) $data['creditNumber'],
        ]);
    }
}
