<?php

namespace App\Services;

use App\Models\ClientGroup;
use App\Models\Customer;
use App\Models\ForecastCreditNote;
use App\Models\RequestClassification;
use App\Models\RequestReason;
use App\Models\RequestType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForecastCreditNoteService
{
    private const CUMPLIMIENTO_THRESHOLD = 97.0;

    private const REQUEST_TYPE_NAME  = 'auditor credits';
    private const CLASSIFICATION_NAME = 'SALES FORECAST';
    private const REASON_NAME         = 'REBATE';

    private const MESES_ES = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
        7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly RequestCrudService $requestCrudService,
        private readonly RequestNumberService $requestNumberService,
    ) {
    }

    /** Historial de NC generadas desde forecast para una entidad (cliente o grupo). */
    public function getHistory(string $tipo, string $id): \Illuminate\Support\Collection
    {
        return ForecastCreditNote::with('request')
            ->where('entityType', $tipo)
            ->where('entityId', (int) $id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    /**
     * Genera la NC (registro en `requests`, tipo "auditor credits") por cumplimiento de forecast
     * de un cliente o grupo en un mes/año, y deja rastro en `forecast_credit_notes`.
     */
    public function generate(string $tipo, string $id, int $year, int $month, mixed $authUser): ForecastCreditNote
    {
        if (!in_array($tipo, ['cliente', 'grupo'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Solo se pueden generar notas de crédito para cliente o grupo.']);
        }

        if (
            ForecastCreditNote::where('entityType', $tipo)
                ->where('entityId', (int) $id)
                ->where('year', $year)
                ->where('month', $month)
                ->exists()
        ) {
            throw ValidationException::withMessages(['month' => 'Ya se generó una nota de crédito para este cliente/grupo en este periodo.']);
        }

        $group = null;
        if ($tipo === 'grupo') {
            $group = ClientGroup::with('members')->findOrFail($id);
            $customerNumber = $group->clientNumber;
            if (empty($customerNumber)) {
                throw ValidationException::withMessages(['customerNumber' => 'El grupo no tiene un número de cliente configurado.']);
            }
            $memberIds = $group->members->pluck('clientId')->unique()->values()->all();
            $summary   = $this->forecastService->getGroupSummary($id, $year);
        } else {
            $customerNumber = (string) $id;
            $memberIds      = [$customerNumber];
            $summary        = $this->forecastService->getClientSummary((int) $id, $year);
        }

        $monthRow = collect($summary['meses'])->firstWhere('mes', $month);

        if (
            !$monthRow
            || $monthRow['porcentajeCumplimiento'] === null
            || $monthRow['porcentajeCumplimiento'] < self::CUMPLIMIENTO_THRESHOLD
        ) {
            throw ValidationException::withMessages(['month' => 'El periodo no alcanzó el cumplimiento mínimo (97%).']);
        }

        $returnPercentage = $monthRow['porcentajeRetorno'];
        if ($returnPercentage === null || $returnPercentage <= 0) {
            throw ValidationException::withMessages(['returnPercentage' => 'No hay % de retorno configurado para este cliente/grupo.']);
        }

        $ventaMensual = (float) $monthRow['ventaMensual'];
        $totalAmount  = round($ventaMensual * $returnPercentage / 100, 2);

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El monto calculado de la nota de crédito no es válido.']);
        }

        $folios = $this->consideredFolios($memberIds, $year, $month);

        if (empty($folios)) {
            throw ValidationException::withMessages(['invoices' => 'No hay facturas consideradas para este periodo (todo excluido por clasificación de producto).']);
        }

        $area = Customer::where('idClient', (int) $customerNumber)->value('area');

        $comments = sprintf(
            '01 Nota de credito del Programa Forecast %d , %s%% de reembolso de las compras totales participantes facturadas durante %s/%d aplicado a las siguientes facturas:%s',
            $year,
            number_format((float) $returnPercentage, 1),
            self::MESES_ES[$month],
            $year,
            implode(',', $folios)
        );

        $requestTypeId     = $this->requiredId(RequestType::class, self::REQUEST_TYPE_NAME, 'requestType');
        $classificationId  = $this->requiredId(RequestClassification::class, self::CLASSIFICATION_NAME, 'classification');
        $reasonId          = $this->requiredId(RequestReason::class, self::REASON_NAME, 'reason');

        return DB::transaction(function () use (
            $tipo, $id, $year, $month, $authUser, $customerNumber, $area, $comments,
            $totalAmount, $ventaMensual, $returnPercentage, $folios,
            $requestTypeId, $classificationId, $reasonId
        ) {
            $reserved = $this->requestNumberService->reserveRequestNumber($requestTypeId, (int) $authUser->id);

            $request = $this->requestCrudService->createRequest([
                'requestNumber'    => $reserved['requestNumber'],
                'requestTypeId'    => $requestTypeId,
                'customerId'       => $customerNumber,
                'requestDate'      => now()->toDateString(),
                'currency'         => 'USD',
                'area'             => $area,
                'reasonId'         => $reasonId,
                'classificationId' => $classificationId,
                'amount'           => $totalAmount,
                'totalAmount'      => $totalAmount,
                'hasIva'           => false,
                'comments'         => $comments,
            ], $authUser);

            return ForecastCreditNote::create([
                'requestId'        => $request->id,
                'entityType'       => $tipo,
                'entityId'         => (int) $id,
                'customerNumber'   => $customerNumber,
                'year'             => $year,
                'month'            => $month,
                'returnPercentage' => $returnPercentage,
                'salesAmount'      => $ventaMensual,
                'totalAmount'      => $totalAmount,
                'invoiceFolios'    => implode(',', $folios),
                'generatedBy'      => $authUser->id,
            ])->load('request');
        });
    }

    /** Folios de facturas de esos clientes en el mes cuyo total considerado (post-clasificación) es > 0. */
    private function consideredFolios(array $clientIds, int $year, int $month): array
    {
        $folios = [];

        foreach ($clientIds as $clientId) {
            $entries = $this->forecastService->getInvoiceProductsByMonth((string) $clientId, $month, $year);

            foreach ($entries as $entry) {
                if (($entry['breakdown']['totalConsiderado'] ?? 0) > 0) {
                    $folios[] = $entry['folio'];
                }
            }
        }

        return array_values(array_unique($folios));
    }

    private function requiredId(string $modelClass, string $name, string $label): int
    {
        $id = $modelClass::where('name', $name)->value('id');

        if (!$id) {
            throw ValidationException::withMessages([$label => "Catálogo '{$name}' no configurado ({$label})."]);
        }

        return (int) $id;
    }
}
