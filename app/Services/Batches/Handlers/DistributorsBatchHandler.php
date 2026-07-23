<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\User;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\DistributorService;
use RuntimeException;

class DistributorsBatchHandler extends AbstractBatchHandler
{
    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly DistributorService $distributorService,
    ) {
    }

    public function batchType(): string
    {
        return 'distributors';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para distributors.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    public function process(array $row, Batch $batch): ?int
    {
        $salesEngineerId = $this->resolveUserId($row, ['salesengineerid', 'sales_engineer_id', 'salesengineer', 'sales_engineer'], 'Sales Engineer');
        $salesManagerId  = $this->resolveUserId($row, ['salesmanagerid', 'sales_manager_id', 'salesmanager', 'sales_manager'], 'Sales Manager');

        $payload = [
            'clientNumber' => $this->value($row, ['clientnumber', 'client_number']),
            'businessName' => $this->value($row, ['businessname', 'business_name', 'razonsocial', 'razon_social']),
            'taxId'        => $this->value($row, ['taxid', 'tax_id', 'rfc']),
            'address'      => $this->value($row, ['address', 'direccion', 'domicilio']),
            'emails'       => $this->value($row, ['emails', 'correos', 'email']),
            'countrycode'  => $this->value($row, ['countrycode', 'country_code', 'pais', 'país']),
        ];

        $validated = $this->validateRow($payload, [
            'clientNumber' => ['required', 'string', 'max:255'],
            'businessName' => ['required', 'string', 'max:255'],
            'taxId'        => ['required', 'string', 'max:20'],
            'address'      => ['required', 'string'],
            'emails'       => ['required', 'string'],
            'countrycode'  => ['required', 'string', 'max:5'],
        ]);

        $clientNumber = (string) $validated['clientNumber'];

        $this->ensureClientNumberIsNotDuplicatedInBatch($batch, $clientNumber);

        if ($this->distributorService->existsByClientNumber($clientNumber)) {
            throw new RuntimeException("El distribuidor con número de cliente '{$clientNumber}' ya existe.");
        }

        $this->distributorService->createByClientNumber($clientNumber, [
            'businessName'    => $validated['businessName'],
            'taxId'           => $validated['taxId'],
            'address'         => $validated['address'],
            'emails'          => $validated['emails'],
            'countrycode'     => $validated['countrycode'],
            'salesEngineerId' => $salesEngineerId,
            'salesManagerId'  => $salesManagerId,
        ]);

        return null;
    }

    private function ensureClientNumberIsNotDuplicatedInBatch(Batch $batch, string $clientNumber): void
    {
        $matches = 0;

        BatchItem::query()
            ->where('batchId', (int) $batch->id)
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$matches, $clientNumber) {
                foreach ($items as $item) {
                    $rawData = is_array($item->rawData)
                        ? $item->rawData
                        : (json_decode((string) $item->rawData, true) ?: []);

                    $rawClientNumber = $this->value($rawData, ['clientnumber', 'client_number']);

                    if (trim((string) $rawClientNumber) === $clientNumber) {
                        $matches++;
                    }
                }
            });

        if ($matches > 1) {
            throw new RuntimeException("El clientNumber '{$clientNumber}' está repetido dentro del archivo de carga.");
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveUserId(array $row, array $aliases, string $label): ?int
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $userById = User::find((int) $value);
            if ($userById) {
                return (int) $userById->id;
            }
        }

        $user = User::where('fullName', trim((string) $value))->first();
        if ($user) {
            return (int) $user->id;
        }

        throw new RuntimeException("{$label} no encontrado: '{$value}'");
    }
}
