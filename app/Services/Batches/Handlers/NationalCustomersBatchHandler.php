<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\NationalCustomerService;
use RuntimeException;

class NationalCustomersBatchHandler extends AbstractBatchHandler
{
    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly NationalCustomerService $nationalCustomerService,
    ) {
    }

    public function batchType(): string
    {
        return 'nationalCustomers';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para nationalCustomers.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    public function process(array $row, Batch $batch): ?int
    {
        $payload = [
            'customerNumber'   => $this->value($row, ['customernumber', 'customer_number', 'clientnumber', 'client_number', 'idcliente']),
            'emails'           => $this->value($row, ['emails', 'correos', 'email', 'correo']),
            'returnPercentage' => $this->value($row, ['returnpercentage', 'return_percentage', 'porcentajeretorno', 'porcentaje_retorno', 'porcentaje']),
        ];

        $validated = $this->validateRow($payload, [
            'customerNumber'   => ['required', 'string', 'max:50'],
            'emails'           => ['required', 'string'],
            'returnPercentage' => ['required', 'numeric', 'between:0,100'],
        ]);

        $customerNumber = trim((string) $validated['customerNumber']);
        $emails = $this->validateEmails((string) $validated['emails']);

        $this->ensureCustomerNumberIsNotDuplicatedInBatch($batch, $customerNumber);

        if (!$this->nationalCustomerService->existsInInvoices($customerNumber)) {
            throw new RuntimeException("El customer number '{$customerNumber}' no existe en la base de datos de invoices.");
        }

        $this->nationalCustomerService->upsertByCustomerNumber($customerNumber, [
            'emails'           => $emails,
            'returnPercentage' => (float) $validated['returnPercentage'],
        ]);

        return null;
    }

    private function validateEmails(string $rawEmails): string
    {
        $addresses = array_filter(array_map('trim', preg_split('/[,;]+/', $rawEmails) ?: []));

        if (count($addresses) === 0) {
            throw new RuntimeException('Debe indicar al menos un correo electrónico.');
        }

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException("El correo electrónico '{$address}' no es válido.");
            }
        }

        return implode(',', $addresses);
    }

    private function ensureCustomerNumberIsNotDuplicatedInBatch(Batch $batch, string $customerNumber): void
    {
        $matches = 0;

        BatchItem::query()
            ->where('batchId', (int) $batch->id)
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$matches, $customerNumber) {
                foreach ($items as $item) {
                    $rawData = is_array($item->rawData)
                        ? $item->rawData
                        : (json_decode((string) $item->rawData, true) ?: []);

                    $rawCustomerNumber = $this->value($rawData, ['customernumber', 'customer_number', 'clientnumber', 'client_number', 'idcliente']);

                    if (trim((string) $rawCustomerNumber) === $customerNumber) {
                        $matches++;
                    }
                }
            });

        if ($matches > 1) {
            throw new RuntimeException("El customer number '{$customerNumber}' está repetido dentro del archivo de carga.");
        }
    }
}
