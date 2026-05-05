<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\DB;
use App\Models\RequestClassification;
use App\Models\RequestReason;
use App\Models\RequestType;
use App\Models\User;
use App\Services\BanxicoService;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\RequestNumberService;
use App\Services\RequestWorkflowService;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use RuntimeException;

class NewRequestBatchHandler extends AbstractBatchHandler
{
    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly RequestNumberService $requestNumberService,
        private readonly RequestWorkflowService $requestWorkflowService,
        private readonly BanxicoService $banxicoService,
    ) {
    }

    public function batchType(): string
    {
        return 'newRequest';
    }

    public function buildRows(BatchInputContext $context): array
    {
        $file = $context->storedFiles[0] ?? null;
        if (!$file) {
            throw new RuntimeException('No se recibió archivo para newRequest.');
        }

        $rows = $this->fileParser->parseByStoredFile((string) $file['storedPath'], (string) $file['extension']);
        foreach ($rows as &$row) {
            $row['requesttypeid'] = $context->requestTypeId;
            $row['defaultuserid'] = $context->authUserId;
        }
        unset($row);

        return $rows;
    }

    public function process(array $row, Batch $batch): ?int
    {
        $requestTypeId = (int) $this->value($row, ['requesttypeid', 'request_type_id']);

        $moduleConfig = Config::get("bulk_upload.new_request.modules.$requestTypeId", []);
        if (!is_array($moduleConfig) || empty($moduleConfig['fields'])) {
            throw new RuntimeException('No hay configuración de campos para requestTypeId=' . $requestTypeId);
        }

        $payload = [
            'requestTypeId' => $requestTypeId,
            'userId' => (int) $this->value($row, ['userid', 'user_id', 'defaultuserid'], (int) $batch->userId),
            'requestNumber' => $this->value($row, ['requestnumber', 'request_number']),
            'status' => (string) $this->value($row, ['status'], 'created'),
        ];

        $requiredFields = [];
        $resolvedCustomer = null;

        foreach ((array) $moduleConfig['fields'] as $fieldConfig) {
            $fieldName = (string) ($fieldConfig['name'] ?? '');
            if ($fieldName === '') {
                continue;
            }

            $aliases = (array) ($fieldConfig['aliases'] ?? []);
            if ($fieldName === 'customerId' || $fieldName === 'idCustomer') {
                $resolvedCustomer = $this->resolveCustomer($row, $aliases);
                $payload['customerId'] = (string) $resolvedCustomer->idCliente;
            } elseif ($fieldName === 'reasonId') {
                $payload['reasonId'] = $this->resolveReasonId($row, $aliases);
            } elseif ($fieldName === 'classificationId') {
                $payload['classificationId'] = $this->resolveClassificationId($row, $aliases);
            } else {
                $payload[$fieldName] = $this->value($row, $aliases);
            }

            if (($fieldConfig['required'] ?? false) === true) {
                $requiredFields[] = $fieldName === 'idCustomer' ? 'customerId' : $fieldName;
            }
        }

        $payload['hasIva'] = $this->boolFromMixed($payload['hasIva'] ?? null, true);
        $payload['hasRga'] = $this->boolFromMixed($payload['hasRga'] ?? null, false);
        $payload['hasReplenishmentIva'] = $this->boolFromMixed($payload['hasReplenishmentIva'] ?? null, false);
        $payload['hasWarehouseIva'] = $this->boolFromMixed($payload['hasWarehouseIva'] ?? null, false);

        // Convertir fechas a formato YYYY-MM-DD
        $payload['requestDate'] = $this->dateFromMixed($payload['requestDate'] ?? null)
            ?? now()->toDateString();
        if (isset($payload['invoiceDate'])) {
            $payload['invoiceDate'] = $this->dateFromMixed($payload['invoiceDate']);
        }

        foreach (['amount', 'replenishmentAmount', 'warehouseAmount'] as $numericField) {
            if (array_key_exists($numericField, $payload)) {
                $payload[$numericField] = $this->floatFromMixed($payload[$numericField], 0);
            }
        }

        $currency = mb_strtoupper((string) ($payload['currency'] ?? ''));
        $payload['exchangeRate'] = $currency === 'USD'
            ? $this->banxicoService->getCurrentUsdRate()
            : 1;

        $rules = [
            'requestTypeId' => ['required', 'integer', Rule::exists((new RequestType())->getTable(), 'id')],
            'userId' => ['required', 'integer', Rule::exists((new User())->getTable(), 'id')],
            'requestNumber' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'requestDate' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'customerId' => ['nullable', 'string', Rule::exists('clientes_tme700618rc7', 'idCliente')],
            'area' => ['nullable', 'string', 'max:150'],
            'reasonId' => ['nullable', 'integer', Rule::exists((new RequestReason())->getTable(), 'id')],
            'classificationId' => ['nullable', 'integer', Rule::exists((new RequestClassification())->getTable(), 'id')],
            'deliveryNote' => ['nullable', 'string', 'max:255'],
            'invoiceNumber' => ['nullable', 'string', 'max:255'],
            'invoiceDate' => ['nullable', 'date'],
            'exchangeRate' => ['required', 'numeric', 'min:0'],
            'creditNumber' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'hasIva' => ['nullable', 'boolean'],
            'orderNumber' => ['nullable', 'string', 'max:255'],
            'creditDebitRefId' => ['nullable', 'string', 'max:255'],
            'newInvoice' => ['nullable', 'string', 'max:255'],
            'warehouseCode' => ['nullable', 'string', 'max:255'],
            'replenishmentAmount' => ['nullable', 'numeric', 'min:0'],
            'hasReplenishmentIva' => ['nullable', 'boolean'],
            'warehouseAmount' => ['nullable', 'numeric', 'min:0'],
            'hasWarehouseIva' => ['nullable', 'boolean'],
            'sapReturnOrder' => ['nullable', 'string', 'max:255'],
            'hasRga' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];

        foreach ($requiredFields as $field) {
            if (isset($rules[$field])) {
                array_unshift($rules[$field], 'required');
            }
        }

        $validated = $this->validateRow($payload, $rules);

        $amount = (float) ($validated['amount'] ?? 0);
        $hasIva = (bool) ($validated['hasIva'] ?? true);
        $iva = $hasIva ? round($amount * 0.16, 2) : 0;
        $totalAmount = round($amount + $iva, 2);

        $replenishmentAmount = (float) ($validated['replenishmentAmount'] ?? 0);
        $hasReplenishmentIva = (bool) ($validated['hasReplenishmentIva'] ?? false);
        $replenishmentIvaValue = $hasReplenishmentIva ? round($replenishmentAmount * 0.16, 2) : 0;
        $replenishmentTotal = round($replenishmentAmount + $replenishmentIvaValue, 2);

        $warehouseAmount = (float) ($validated['warehouseAmount'] ?? 0);
        $hasWarehouseIva = (bool) ($validated['hasWarehouseIva'] ?? false);
        $warehouseIvaValue = $hasWarehouseIva ? round($warehouseAmount * 0.16, 2) : 0;
        $warehouseTotal = round($warehouseAmount + $warehouseIvaValue, 2);

        $requestNumber = $validated['requestNumber']
            ?? $this->requestNumberService->generateRequestNumber((int) $validated['requestTypeId']);

        $request = RequestModel::create([
            'requestNumber' => $requestNumber,
            'requestTypeId' => (int) $validated['requestTypeId'],
            'userId' => (int) $validated['userId'],
            'customerId' => $validated['customerId'] ?? null,
            'status' => $validated['status'] ?? 'created',
            'orderNumber' => $validated['orderNumber'] ?? null,
            'requestDate' => $validated['requestDate'],
            'currency' => $validated['currency'],
            'area' => $validated['area'] ?? null,
            'reasonId' => isset($validated['reasonId']) ? (int) $validated['reasonId'] : null,
            'classificationId' => isset($validated['classificationId']) ? (int) $validated['classificationId'] : null,
            'deliveryNote' => $validated['deliveryNote'] ?? null,
            'invoiceNumber' => $validated['invoiceNumber'] ?? null,
            'invoiceDate' => $validated['invoiceDate'] ?? null,
            'exchangeRate' => (float) ($validated['exchangeRate'] ?? 1),
            'creditNumber' => $validated['creditNumber'] ?? null,
            'amount' => $amount,
            'hasIva' => $hasIva,
            'totalAmount' => $totalAmount,
            'creditDebitRefId' => $validated['creditDebitRefId'] ?? null,
            'newInvoice' => $validated['newInvoice'] ?? null,
            'warehouseCode' => $validated['warehouseCode'] ?? null,
            'replenishmentAmount' => $replenishmentAmount,
            'hasReplenishmentIva' => $hasReplenishmentIva,
            'replenishmentTotal' => $replenishmentTotal,
            'warehouseAmount' => $warehouseAmount,
            'hasWarehouseIva' => $hasWarehouseIva,
            'warehouseTotal' => $warehouseTotal,
            'sapReturnOrder' => $validated['sapReturnOrder'] ?? null,
            'hasRga' => (bool) ($validated['hasRga'] ?? false),
            'comments' => $validated['comments'] ?? null,
        ]);

        $this->requestWorkflowService->assignRequestToWorkflow($request, (int) $validated['userId']);

        return (int) $request->id;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveCustomer(array $row, array $aliases): ?object
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        \Log::error('[resolveCustomer] value', ['value' => $value, 'type' => gettype($value)]);

        $customer = DB::table('clientes_tme700618rc7')
            ->where('idCliente', (string) $value)
            ->first();

        if ($customer) {
            return $customer;
        }

        \Log::error('[resolveCustomer] no encontrado', ['value' => $value, 'query_result' => $customer]);
        throw new RuntimeException("Cliente no encontrado: '{$value}'");
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveReasonId(array $row, array $aliases): ?int
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        // Si es numérico, buscar por ID
        if (is_numeric($value)) {
            $reasonById = RequestReason::find((int) $value);
            if ($reasonById) {
                return (int) $reasonById->id;
            }
        }

        // Buscar por name
        $reason = RequestReason::where('name', (string) $value)->first();
        if ($reason) {
            return (int) $reason->id;
        }

        // No se encontró la razón
        throw new RuntimeException("Razón de solicitud no encontrada: '{$value}'");
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveClassificationId(array $row, array $aliases): ?int
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        // Si es numérico, buscar por ID
        if (is_numeric($value)) {
            $classificationById = RequestClassification::find((int) $value);
            if ($classificationById) {
                return (int) $classificationById->id;
            }
        }

        // Buscar por name
        $classification = RequestClassification::where('name', (string) $value)->first();
        if ($classification) {
            return (int) $classification->id;
        }

        // No se encontró la clasificación
        throw new RuntimeException("Clasificación de solicitud no encontrada: '{$value}'");
    }
}
