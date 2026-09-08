<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\NationalCustomer;
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

    public function buildRows(BatchInputContext $context): iterable
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para nationalCustomers.');
        }

        return $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
    }

    /**
     * Cada campo actualizable es opcional e independiente: una fila puede traer solo la
     * moneda, solo el correo o solo el porcentaje. Lo que venga vacío no se toca, así que
     * el cliente conserva el valor que ya tenía guardado.
     */
    public function process(array $row, Batch $batch): ?int
    {
        $payload = [
            'customerNumber'   => $this->value($row, ['customernumber', 'customer_number', 'clientnumber', 'client_number', 'idcliente']),
            'sapName'          => $this->blankToNull($this->value($row, ['sapname', 'sap_name', 'nombresap', 'nombre_sap'])),
            'emails'           => $this->blankToNull($this->value($row, ['emails', 'correos', 'email', 'correo'])),
            'returnPercentage' => $this->blankToNull($this->value($row, ['returnpercentage', 'return_percentage', 'porcentajeretorno', 'porcentaje_retorno', 'porcentaje'])),
            'currency'         => $this->blankToNull($this->value($row, ['currency', 'moneda', 'divisa'])),
        ];

        $validated = $this->validateRow($payload, [
            'customerNumber'   => ['required', 'string', 'max:50'],
            'sapName'          => ['nullable', 'string', 'max:255'],
            'emails'           => ['nullable', 'string'],
            'returnPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'currency'         => ['nullable', 'string'],
        ]);

        $customerNumber = trim((string) $validated['customerNumber']);

        $this->ensureCustomerNumberIsNotDuplicatedInBatch($batch, $customerNumber);

        if (!$this->nationalCustomerService->existsInInvoices($customerNumber)) {
            throw new RuntimeException("El customer number '{$customerNumber}' no existe en la base de datos de invoices.");
        }

        $data = [];

        if (($validated['sapName'] ?? null) !== null) {
            $data['sapName'] = trim((string) $validated['sapName']);
        }

        if (($validated['emails'] ?? null) !== null) {
            $data['emails'] = $this->validateEmails((string) $validated['emails']);
        }

        if (($validated['returnPercentage'] ?? null) !== null) {
            $data['returnPercentage'] = (float) $validated['returnPercentage'];
        }

        $currency = $this->normalizeCurrency($validated['currency'] ?? null);
        if ($currency !== null) {
            $data['currency'] = $currency;
        }

        // Sin campos: la fila solo da de alta al cliente en el padrón (o lo deja como está).
        $this->nationalCustomerService->upsertByCustomerNumber($customerNumber, $data);

        return null;
    }

    /** Celda vacía = campo no enviado; una cadena en blanco no debe borrar lo ya guardado. */
    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function normalizeCurrency(mixed $rawCurrency): ?string
    {
        $currency = strtoupper(trim((string) $rawCurrency));

        if ($currency === '') {
            return null;
        }

        if (!in_array($currency, NationalCustomer::CURRENCIES, true)) {
            throw new RuntimeException("La moneda '{$currency}' no es válida. Use USD o MXN.");
        }

        return $currency;
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
