<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Request as RequestModel;
use App\Models\RequestClassification;
use App\Models\RequestReason;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRequestCurrentStep;
use App\Models\WorkflowRequestHistory;
use App\Models\WorkflowRequestStep;
use App\Models\WorkflowStep;
use App\Services\Batches\BatchInputContext;
use App\Services\Batches\Parsers\BulkFileParser;
use App\Services\RequestNumberService;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class NewRequestBatchHandler extends AbstractBatchHandler
{
    public function __construct(
        private readonly BulkFileParser $fileParser,
        private readonly RequestNumberService $requestNumberService
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
                $payload['customerId'] = (int) $resolvedCustomer->idCustomer;
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

        foreach (['exchangeRate', 'amount', 'replenishmentAmount', 'warehouseAmount'] as $numericField) {
            if (array_key_exists($numericField, $payload)) {
                $payload[$numericField] = $this->floatFromMixed($payload[$numericField], 0);
            }
        }

        if (!array_key_exists('exchangeRate', $payload) || $payload['exchangeRate'] === 0.0) {
            $payload['exchangeRate'] = 1;
        }

        $rules = [
            'requestTypeId' => ['required', 'integer', Rule::exists((new RequestType())->getTable(), 'id')],
            'userId' => ['required', 'integer', Rule::exists((new User())->getTable(), 'id')],
            'requestNumber' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'requestDate' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'customerId' => ['nullable', 'integer', Rule::exists((new Customer())->getTable(), 'idCustomer')],
            'area' => ['nullable', 'string', 'max:150'],
            'reasonId' => ['nullable', 'integer', Rule::exists((new RequestReason())->getTable(), 'id')],
            'classificationId' => ['nullable', 'integer', Rule::exists((new RequestClassification())->getTable(), 'id')],
            'deliveryNote' => ['nullable', 'string', 'max:255'],
            'invoiceNumber' => ['nullable', 'string', 'max:255'],
            'invoiceDate' => ['nullable', 'date'],
            'exchangeRate' => ['nullable', 'numeric', 'min:0'],
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

        $this->assignRequestToWorkflow($request, (int) $validated['userId']);

        return (int) $request->id;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    private function resolveCustomer(array $row, array $aliases): ?Customer
    {
        $value = $this->value($row, $aliases);

        if ($value === null || $value === '') {
            return null;
        }

        // Si es numérico, intentar por idCustomer y luego por idClient
        if (is_numeric($value)) {
            $number = (int) $value;

            $customerById = Customer::query()->where('idCustomer', $number)->first();
            if ($customerById) {
                return $customerById;
            }

            $customerByClientId = Customer::query()->where('idClient', $number)->first();
            if ($customerByClientId) {
                return $customerByClientId;
            }
        }

        // En este sistema, también permitimos idClient como texto
        $customer = Customer::query()->where('idClient', (int) $value)->first();
        if ($customer) {
            return $customer;
        }

        // No se encontró el cliente
        throw new RuntimeException("Cliente no encontrado: '{$value}'");
    }

    private function assignRequestToWorkflow(RequestModel $requestModel, int $actionUserId): void
    {
        $classification = RequestClassification::find($requestModel->classificationId);

        if (!$classification) {
            throw ValidationException::withMessages([
                'classificationId' => ['No existe la clasificacion seleccionada.'],
            ]);
        }

        $isTypeLinkedToClassification = $classification->requestTypes()
            ->where('id', $requestModel->requestTypeId)
            ->exists();

        if (!$isTypeLinkedToClassification) {
            throw ValidationException::withMessages([
                'classificationId' => ['La clasificacion no pertenece al tipo de solicitud indicado.'],
            ]);
        }

        $workflow = Workflow::query()
            ->where('requestTypeId', $requestModel->requestTypeId)
            ->where('classificationType', $classification->type)
            ->where('isActive', true)
            ->orderBy('id')
            ->first();

        if (!$workflow) {
            throw ValidationException::withMessages([
                'workflow' => ['No existe un workflow activo para el tipo de solicitud y la clasificacion.type.'],
            ]);
        }

        $initialStep = WorkflowStep::query()
            ->where('workflowId', $workflow->id)
            ->where('isInitialStep', true)
            ->orderBy('stepOrder')
            ->first();

        if (!$initialStep) {
            throw ValidationException::withMessages([
                'workflowStep' => ['El workflow seleccionado no tiene paso inicial configurado.'],
            ]);
        }

        $requestStep = WorkflowRequestStep::create([
            'requestId' => $requestModel->id,
            'workflowStepId' => $initialStep->id,
            'assignedRoleId' => $initialStep->roleId,
            'status' => 'pending',
            'startedAt' => now(),
        ]);

        WorkflowRequestCurrentStep::updateOrCreate(
            ['requestId' => $requestModel->id],
            [
                'workflowId' => $workflow->id,
                'workflowStepId' => $initialStep->id,
                'assignedRoleId' => $initialStep->roleId,
                'status' => 'pending',
            ]
        );

        WorkflowRequestHistory::create([
            'requestWorkflowStepId' => $requestStep->id,
            'requestId' => $requestModel->id,
            'workflowStepId' => $initialStep->id,
            'actionUserId' => $actionUserId,
            'actionType' => 'created',
            'comments' => 'Solicitud creada y asignada al flujo inicial.',
        ]);
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
