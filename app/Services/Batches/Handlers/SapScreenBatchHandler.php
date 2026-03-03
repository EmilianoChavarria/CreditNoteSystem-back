<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Services\Batches\BatchInputContext;
use RuntimeException;

class SapScreenBatchHandler extends AbstractBatchHandler
{
    public function batchType(): string
    {
        return 'sapScreen';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $rows = [];

        foreach ($context->storedFiles as $file) {
            $nameWithoutExtension = pathinfo((string) $file['originalName'], PATHINFO_FILENAME);
            preg_match('/^(\d+)_([A-Za-z0-9\-]+)$/', $nameWithoutExtension, $matches);

            $rows[] = [
                'requestnumber' => $matches[1] ?? null,
                'creditnumber' => $matches[2] ?? null,
                'file' => $file,
                'formatError' => count($matches) < 3,
            ];
        }

        return $rows;
    }

    public function process(array $row, Batch $batch): void
    {
        if (($row['formatError'] ?? false) === true) {
            throw new RuntimeException('Nombre de archivo inválido. Formato esperado: {requestNumber}_{creditNumber}.pdf');
        }

        $data = $this->validateRow([
            'requestnumber' => $this->value($row, ['requestnumber', 'request_number']),
        ], [
            'requestnumber' => ['required', 'integer'],
        ]);

        $request = RequestModel::where('requestNumber', (int) $data['requestnumber'])->first();

        if (!$request) {
            throw new RuntimeException('No existe request para requestNumber=' . $data['requestnumber']);
        }

        $this->createAttachment($request, (array) ($row['file'] ?? []));

        $creditNumber = $this->value($row, ['creditnumber', 'credit_number']);
        if ($creditNumber !== null && $creditNumber !== '') {
            $request->update(['creditNumber' => (string) $creditNumber]);
        }
    }
}
