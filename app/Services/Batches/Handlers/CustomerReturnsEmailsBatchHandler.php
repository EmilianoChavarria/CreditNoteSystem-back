<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\CustomerQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Carga masiva de correos de recordatorio de devoluciones por cliente.
 * Columnas: Customer Number + Emails (separados por ";" o ","). Un mismo cliente
 * puede repetirse en varias filas: buildRows junta sus correos en un solo item, y
 * process reemplaza los correos guardados del cliente por los del archivo.
 */
class CustomerReturnsEmailsBatchHandler extends AbstractBatchHandler
{
    private const CONNECTION = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';

    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly CustomerQueryService $customerQueryService,
    ) {
    }

    public function batchType(): string
    {
        return 'customerReturnsEmails';
    }

    public function buildRows(BatchInputContext $context): iterable
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para customerReturnsEmails.');
        }

        $groups = [];
        $withoutCustomer = [];

        foreach ($this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']) as $row) {
            $customerNumber = trim((string) $this->value($row, ['customernumber', 'customer_number', 'clientnumber', 'client_number', 'idcliente', 'numerocliente', 'cliente'], ''));
            $emails = $this->splitEmails((string) $this->value($row, ['emails', 'email', 'correos', 'correo'], ''));
            $rowNumber = (int) ($row['_rowNumber'] ?? 0);

            if ($customerNumber === '') {
                $withoutCustomer[] = ['customerNumber' => '', 'emails' => implode(';', $emails), 'rows' => (string) $rowNumber];
                continue;
            }

            $groups[$customerNumber]['emails'] = array_merge($groups[$customerNumber]['emails'] ?? [], $emails);
            $groups[$customerNumber]['rows'][] = $rowNumber;
        }

        foreach ($groups as $customerNumber => $group) {
            yield [
                'customerNumber' => (string) $customerNumber,
                'emails' => implode(';', array_values(array_unique($group['emails']))),
                'rows' => implode(',', $group['rows']),
            ];
        }

        foreach ($withoutCustomer as $row) {
            yield $row;
        }
    }

    public function process(array $row, Batch $batch): ?int
    {
        $validated = $this->validateRow([
            'customerNumber' => $this->value($row, ['customernumber']),
            'emails' => $this->value($row, ['emails']),
        ], [
            'customerNumber' => ['required', 'string', 'max:50'],
            'emails' => ['required', 'string'],
        ], [
            'customerNumber.required' => 'Falta el número de cliente.',
            'emails.required' => 'Debe indicar al menos un correo electrónico.',
        ]);

        $customerNumber = trim((string) $validated['customerNumber']);
        $emails = $this->splitEmails((string) $validated['emails']);

        foreach ($emails as $email) {
            if (Validator::make(['email' => $email], ['email' => ['email']])->fails()) {
                throw new RuntimeException("Correo inválido: '{$email}'.");
            }
        }

        $exists = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->where('idCliente', $customerNumber)
            ->exists();

        if (!$exists) {
            throw new RuntimeException("El cliente '{$customerNumber}' no existe.");
        }

        $this->customerQueryService->updateReturnsEmails((int) $customerNumber, $emails);

        return null;
    }

    /**
     * @return string[]
     */
    private function splitEmails(string $raw): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', $raw) ?: []))));
    }
}
