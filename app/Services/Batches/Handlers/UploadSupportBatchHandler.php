<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Services\Batches\BatchInputContext;
use RuntimeException;

class UploadSupportBatchHandler extends AbstractBatchHandler
{
    public function batchType(): string
    {
        return 'uploadSupport';
    }

    public function buildRows(BatchInputContext $context): array
    {
        if (!$context->requestTypeId || $context->minRange === null || $context->maxRange === null) {
            throw new RuntimeException('uploadSupport requiere requestTypeId, minRange y maxRange.');
        }

        $requests = RequestModel::query()
            ->where('requestTypeId', $context->requestTypeId)
            ->whereBetween('requestNumber', [$context->minRange, $context->maxRange])
            ->get(['id', 'requestNumber']);

        if ($requests->isEmpty()) {
            throw new RuntimeException('No se encontraron requests para el rango y requestTypeId enviados.');
        }

        $rows = [];
        foreach ($requests as $request) {
            foreach ($context->storedFiles as $file) {
                $rows[] = [
                    'requestId' => $request->id,
                    'requestNumber' => $request->requestNumber,
                    'file' => $file,
                ];
            }
        }

        return $rows;
    }

    public function process(array $row, Batch $batch): ?int
    {
        $data = $this->validateRow([
            'requestId' => $this->value($row, ['requestId', 'requestid']),
        ], [
            'requestId' => ['required', 'integer', 'exists:requests,id'],
        ]);

        $request = RequestModel::find((int) $data['requestId']);
        if (!$request) {
            throw new RuntimeException('Request no encontrada para id=' . $data['requestId']);
        }

        $this->createAttachment($request, (array) ($row['file'] ?? []));

        return (int) $request->id;
    }
}
