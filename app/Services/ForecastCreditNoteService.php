<?php

namespace App\Services;

use App\Models\ClientGroup;
use App\Models\Customer;
use App\Models\ForecastCreditNote;
use App\Models\RequestClassification;
use App\Models\RequestReason;
use App\Models\RequestType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForecastCreditNoteService
{
    private const CUMPLIMIENTO_THRESHOLD = 97.0;

    private const REQUEST_TYPE_NAME   = 'auditor credits';
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

    /**
     * Historial de NC generadas desde forecast.
     * - tipo=cliente: NC generadas directamente para ese cliente (no ligadas a ningún grupo).
     * - tipo=grupo: NC de cada cliente miembro que aportó, generadas cuando ese grupo cumplió el mes.
     */
    public function getHistory(string $tipo, string $id): Collection
    {
        $query = ForecastCreditNote::with('request');

        $query = $tipo === 'grupo'
            ? $query->where('groupId', (int) $id)
            : $query->where('entityType', 'cliente')->where('entityId', (int) $id)->whereNull('groupId');

        return $query->orderByDesc('year')->orderByDesc('month')->get();
    }

    /**
     * Aportación de cada cliente miembro de un grupo en un mes: facturas consideradas, venta
     * considerada, % de participación dentro del grupo, retorno calculado y si ya tiene NC.
     */
    public function getGroupMonthBreakdown(string $groupId, int $year, int $month): array
    {
        $group   = ClientGroup::with('members')->findOrFail($groupId);
        $members = $group->members->pluck('clientId')->unique()->values()->all();

        $returnPercentage = $group->returnPercentage !== null ? (float) $group->returnPercentage : null;

        $rows       = [];
        $totalSales = 0.0;

        foreach ($members as $memberId) {
            [$sales, $folios] = $this->considered((string) $memberId, $year, $month);
            $totalSales += $sales;

            $rows[(string) $memberId] = [
                'clientId'      => (string) $memberId,
                'name'          => $this->forecastService->getClientName((int) $memberId),
                'folioCount'    => count($folios),
                'salesAmount'   => $sales,
                'participation' => null,
                'returnAmount'  => $returnPercentage !== null ? round($sales * $returnPercentage / 100, 2) : null,
                'note'          => null,
            ];
        }

        if ($totalSales > 0) {
            foreach ($rows as &$row) {
                $row['participation'] = round($row['salesAmount'] / $totalSales * 100, 2);
            }
            unset($row);
        }

        $notes = ForecastCreditNote::with('request')
            ->where('entityType', 'cliente')
            ->whereIn('entityId', array_map('intval', $members))
            ->where('year', $year)
            ->where('month', $month)
            ->get();

        foreach ($notes as $note) {
            if (isset($rows[$note->customerNumber])) {
                $rows[$note->customerNumber]['note'] = [
                    'id'            => $note->id,
                    'requestId'     => $note->requestId,
                    'requestNumber' => $note->request?->requestNumber,
                    'requestStatus' => $note->request?->status,
                ];
            }
        }

        return [
            'groupId'          => $group->id,
            'groupName'        => $group->name,
            'year'             => $year,
            'month'            => $month,
            'returnPercentage' => $returnPercentage,
            'totalSales'       => round($totalSales, 2),
            'members'          => array_values($rows),
        ];
    }

    /**
     * Genera NC (registro en `requests`, tipo auditor credits) por cumplimiento de forecast.
     *
     * - cliente: una sola NC para ese cliente.
     * - grupo: el % de retorno es del grupo, pero la NC se reparte una por cada cliente miembro
     *   que aportó ventas consideradas ese mes (cada una a su propio customerId/area).
     *
     * @return array{created: ForecastCreditNote[], skipped: array<int, array{clientId: string, reason: string}>}
     */
    public function generate(string $tipo, string $id, int $year, int $month, mixed $authUser): array
    {
        if (!in_array($tipo, ['cliente', 'grupo'], true)) {
            throw ValidationException::withMessages(['tipo' => 'Solo se pueden generar notas de crédito para cliente o grupo.']);
        }

        $requestTypeId    = $this->requiredId(RequestType::class, self::REQUEST_TYPE_NAME, 'requestType');
        $classificationId = $this->requiredId(RequestClassification::class, self::CLASSIFICATION_NAME, 'classification');
        $reasonId         = $this->requiredId(RequestReason::class, self::REASON_NAME, 'reason');
        $catalogIds       = [$requestTypeId, $classificationId, $reasonId];

        if ($tipo === 'cliente') {
            $summary          = $this->forecastService->getClientSummary((int) $id, $year);
            $returnPercentage = $this->resolveReturnPercentage($summary, $month);

            if ($this->noteExists('cliente', (string) $id, $year, $month)) {
                throw ValidationException::withMessages(['month' => 'Ya se generó una nota de crédito para este cliente en este periodo.']);
            }

            [$sales, $folios] = $this->considered((string) $id, $year, $month);

            if ($sales <= 0 || empty($folios)) {
                throw ValidationException::withMessages(['invoices' => 'No hay facturas consideradas para este periodo (todo excluido por clasificación de producto).']);
            }

            $note = $this->createNote((string) $id, $year, $month, $sales, $returnPercentage, $folios, null, $authUser, ...$catalogIds);

            return ['created' => [$note], 'skipped' => []];
        }

        // grupo
        $group = ClientGroup::with('members')->findOrFail($id);

        if (empty($group->returnPercentage) || $group->returnPercentage <= 0) {
            throw ValidationException::withMessages(['returnPercentage' => 'El grupo no tiene % de retorno configurado.']);
        }

        $summary = $this->forecastService->getGroupSummary($id, $year);
        $this->resolveReturnPercentage($summary, $month); // valida cumplimiento del grupo (lanza si no aplica)
        $returnPercentage = (float) $group->returnPercentage;

        $memberIds = $group->members->pluck('clientId')->unique()->values()->all();

        if (empty($memberIds)) {
            throw ValidationException::withMessages(['members' => 'El grupo no tiene clientes miembro.']);
        }

        $created = [];
        $skipped = [];

        foreach ($memberIds as $memberId) {
            if ($this->noteExists('cliente', (string) $memberId, $year, $month)) {
                $skipped[] = ['clientId' => (string) $memberId, 'reason' => 'Ya tiene una nota de crédito generada este periodo.'];
                continue;
            }

            [$sales, $folios] = $this->considered((string) $memberId, $year, $month);

            if ($sales <= 0 || empty($folios)) {
                $skipped[] = ['clientId' => (string) $memberId, 'reason' => 'Sin ventas consideradas este periodo.'];
                continue;
            }

            $created[] = $this->createNote((string) $memberId, $year, $month, $sales, $returnPercentage, $folios, (int) $group->id, $authUser, ...$catalogIds);
        }

        if (empty($created)) {
            throw ValidationException::withMessages(['members' => 'Ningún cliente del grupo es elegible este periodo (ya tienen NC generada o no aportaron ventas consideradas).']);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Valida cumplimiento (>=97%) y retorna el % de retorno de ese mes; lanza si no aplica. */
    private function resolveReturnPercentage(array $summary, int $month): float
    {
        $monthRow = collect($summary['meses'])->firstWhere('mes', $month);

        if (
            !$monthRow
            || $monthRow['porcentajeCumplimiento'] === null
            || $monthRow['porcentajeCumplimiento'] < self::CUMPLIMIENTO_THRESHOLD
        ) {
            throw ValidationException::withMessages(['month' => 'El periodo no alcanzó el cumplimiento mínimo (97%).']);
        }

        if ($monthRow['porcentajeRetorno'] === null || $monthRow['porcentajeRetorno'] <= 0) {
            throw ValidationException::withMessages(['returnPercentage' => 'No hay % de retorno configurado para este cliente/grupo.']);
        }

        return (float) $monthRow['porcentajeRetorno'];
    }

    private function noteExists(string $entityType, string $clientId, int $year, int $month): bool
    {
        return ForecastCreditNote::where('entityType', $entityType)
            ->where('entityId', (int) $clientId)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    /** [salesConsiderado, folios[]] de un cliente en un mes, excluyendo lo 100% descontado por clasificación. */
    private function considered(string $clientId, int $year, int $month): array
    {
        $entries = $this->forecastService->getInvoiceProductsByMonth($clientId, $month, $year);

        $sales  = 0.0;
        $folios = [];

        foreach ($entries as $entry) {
            $totalConsiderado = (float) ($entry['breakdown']['totalConsiderado'] ?? 0);
            if ($totalConsiderado > 0) {
                $sales += $totalConsiderado;
                $folios[] = $entry['folio'];
            }
        }

        return [round($sales, 2), array_values(array_unique($folios))];
    }

    /** Crea el request (auditor credits) + su registro de historial para un cliente puntual. */
    private function createNote(
        string $clientId,
        int $year,
        int $month,
        float $sales,
        float $returnPercentage,
        array $folios,
        ?int $groupId,
        mixed $authUser,
        int $requestTypeId,
        int $classificationId,
        int $reasonId
    ): ForecastCreditNote {
        $totalAmount = round($sales * $returnPercentage / 100, 2);

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages(['amount' => "Monto de nota de crédito inválido para el cliente {$clientId}."]);
        }

        $area = Customer::where('idClient', (int) $clientId)->value('area');

        $comments = sprintf(
            '01 Nota de credito del Programa Forecast %d , %s%% de reembolso de las compras totales participantes facturadas durante %s/%d aplicado a las siguientes facturas:%s',
            $year,
            number_format($returnPercentage, 1),
            self::MESES_ES[$month],
            $year,
            implode(',', $folios)
        );

        return DB::transaction(function () use (
            $clientId, $year, $month, $sales, $returnPercentage, $folios, $groupId, $authUser,
            $requestTypeId, $classificationId, $reasonId, $totalAmount, $area, $comments
        ) {
            $reserved = $this->requestNumberService->reserveRequestNumber($requestTypeId, (int) $authUser->id);

            $request = $this->requestCrudService->createRequest([
                'requestNumber'    => $reserved['requestNumber'],
                'requestTypeId'    => $requestTypeId,
                'customerId'       => $clientId,
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
                'entityType'       => 'cliente',
                'entityId'         => (int) $clientId,
                'customerNumber'   => $clientId,
                'groupId'          => $groupId,
                'year'             => $year,
                'month'            => $month,
                'returnPercentage' => $returnPercentage,
                'salesAmount'      => $sales,
                'totalAmount'      => $totalAmount,
                'invoiceFolios'    => implode(',', $folios),
                'generatedBy'      => $authUser->id,
            ])->load('request');
        });
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
