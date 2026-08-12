<?php

namespace App\Services;

use App\Models\ClientGroup;
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
    private const IVA_RATE               = 0.16;

    private const REQUEST_TYPE_NAME   = 'auditor credits';
    private const CLASSIFICATION_NAME = 'SALES FORECAST';
    private const REASON_NAME         = 'REBATE';

    private const INVOICES_CONNECTION  = 'invoices';
    private const CLIENT_EXT_TABLE     = 'clientes_TME700618RC7_ext';

    private const MESES_ES = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
        7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    /** @var array<string, array{0: float, 1: string[]}> Memo de considered(), por cliente/periodo/moneda. */
    private array $consideredCache = [];

    public function __construct(
        private readonly ForecastService $forecastService,
        private readonly RequestCrudService $requestCrudService,
        private readonly RequestNumberService $requestNumberService,
        private readonly BanxicoService $banxico,
        private readonly RequestAttachmentService $requestAttachmentService,
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

        // Los totales del grupo van en su moneda; la aportación y el retorno de cada
        // cliente, en la suya, que es la que se usará en su nota de crédito.
        $groupCurrency = $this->forecastService->resolveGroupCurrency($groupId);

        $rows       = [];
        $totalSales = 0.0;

        foreach ($members as $memberId) {
            $memberCurrency = $this->forecastService->resolveClientCurrency((string) $memberId);

            [$sales, $folios] = $this->considered((string) $memberId, $year, $month, $memberCurrency);
            $groupSales       = $this->considered((string) $memberId, $year, $month, $groupCurrency)[0];

            $totalSales += $groupSales;

            $rows[(string) $memberId] = [
                'clientId'         => (string) $memberId,
                'name'             => $this->forecastService->getClientName((int) $memberId),
                'folioCount'       => count($folios),
                'currency'         => $memberCurrency,
                'salesAmount'      => $sales,
                // La misma aportación en la moneda del grupo: es la que hace comparable el %.
                'salesAmountGroup' => $groupSales,
                'participation'    => null,
                'returnAmount'     => $returnPercentage !== null ? round($sales * $returnPercentage / 100, 2) : null,
                'note'             => null,
            ];
        }

        if ($totalSales > 0) {
            foreach ($rows as &$row) {
                $row['participation'] = round($row['salesAmountGroup'] / $totalSales * 100, 2);
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
            'currency'         => $groupCurrency,
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
     * @param array<string, \Illuminate\Http\UploadedFile[]> $attachmentsByClient Adjuntos específicos por clientId.
     * @param \Illuminate\Http\UploadedFile[] $sharedAttachments Adjuntos que aplican a todas las NC generadas;
     *   se usan cuando el clientId no trae los suyos. Cada NC requiere al menos un archivo, el workflow no
     *   avanza una solicitud sin adjuntos.
     * @return array{created: ForecastCreditNote[], skipped: array<int, array{clientId: string, reason: string}>}
     */
    public function generate(string $tipo, string $id, int $year, int $month, mixed $authUser, array $attachmentsByClient = [], array $sharedAttachments = []): array
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

            $currency = $this->forecastService->resolveClientCurrency((string) $id);

            [$sales, $folios] = $this->considered((string) $id, $year, $month, $currency);

            if ($sales <= 0 || empty($folios)) {
                throw ValidationException::withMessages(['invoices' => 'No hay facturas consideradas para este periodo (todo excluido por clasificación de producto).']);
            }

            $files = $this->validFiles($attachmentsByClient[(string) $id] ?? $sharedAttachments);

            if (empty($files)) {
                throw ValidationException::withMessages(['attachments' => 'Debes adjuntar al menos un archivo de soporte para generar la nota de crédito.']);
            }

            $note = $this->createNote((string) $id, $year, $month, $sales, $returnPercentage, $currency, $folios, null, $authUser, $files, ...$catalogIds);

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

            // Cada NC se calcula y emite en la moneda de su propio cliente, no en la del grupo.
            $currency = $this->forecastService->resolveClientCurrency((string) $memberId);

            [$sales, $folios] = $this->considered((string) $memberId, $year, $month, $currency);

            if ($sales <= 0 || empty($folios)) {
                $skipped[] = ['clientId' => (string) $memberId, 'reason' => 'Sin ventas consideradas este periodo.'];
                continue;
            }

            $files = $this->validFiles($attachmentsByClient[(string) $memberId] ?? $sharedAttachments);

            if (empty($files)) {
                $skipped[] = ['clientId' => (string) $memberId, 'reason' => 'Falta adjuntar el archivo de soporte de esta nota.'];
                continue;
            }

            $created[] = $this->createNote((string) $memberId, $year, $month, $sales, $returnPercentage, $currency, $folios, (int) $group->id, $authUser, $files, ...$catalogIds);
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

    /**
     * Registro borrado lógicamente del mismo periodo, si lo hay.
     *
     * El índice único (entityType, entityId, year, month) no distingue el borrado
     * lógico: sin esto, un cliente dado de baja y reactivado no podría volver a
     * generar la nota de ese mes. Se reutiliza la fila (restore + sobrescritura)
     * en vez de borrarla, para no depender del privilegio DELETE en la BD. La
     * auditoría de la nota original se conserva en su request, que no se toca.
     */
    private function findTrashedNote(string $entityType, string $clientId, int $year, int $month): ?ForecastCreditNote
    {
        return ForecastCreditNote::onlyTrashed()
            ->where('entityType', $entityType)
            ->where('entityId', (int) $clientId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * [salesConsiderado, folios[]] de un cliente en un mes, en $currency y excluyendo
     * lo 100% descontado por clasificación. Se memoiza porque el desglose de un grupo
     * pide la misma aportación dos veces (en la moneda del cliente y en la del grupo).
     */
    private function considered(string $clientId, int $year, int $month, string $currency): array
    {
        $key = "{$clientId}|{$year}|{$month}|{$currency}";

        if (isset($this->consideredCache[$key])) {
            return $this->consideredCache[$key];
        }

        $entries = $this->forecastService->getInvoiceProductsByMonth($clientId, $month, $year, $currency);

        $sales  = 0.0;
        $folios = [];

        foreach ($entries as $entry) {
            $totalConsiderado = (float) ($entry['breakdown']['totalConsiderado'] ?? 0);

            if ($totalConsiderado > 0) {
                // Las notas de crédito de devolución restan, igual que en la venta del mes.
                $sales += $totalConsiderado * (int) ($entry['signo'] ?? 1);
                $folios[] = $entry['folio'];
            }
        }

        return $this->consideredCache[$key] = [round($sales, 2), array_values(array_unique($folios))];
    }

    /** Filtra a solo UploadedFile válidos (descarta entradas vacías/corruptas). */
    private function validFiles(array $files): array
    {
        return array_values(array_filter($files, fn ($f) => $f instanceof \Illuminate\Http\UploadedFile && $f->isValid()));
    }

    /** Crea el request (auditor credits) + su registro de historial para un cliente puntual. */
    private function createNote(
        string $clientId,
        int $year,
        int $month,
        float $sales,
        float $returnPercentage,
        string $currency,
        array $folios,
        ?int $groupId,
        mixed $authUser,
        array $files,
        int $requestTypeId,
        int $classificationId,
        int $reasonId
    ): ForecastCreditNote {
        // El retorno es el subtotal; la NC del programa forecast siempre lleva IVA.
        $amount      = round($sales * $returnPercentage / 100, 2);
        $totalAmount = round($amount * (1 + self::IVA_RATE), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => "Monto de nota de crédito inválido para el cliente {$clientId}."]);
        }

        $area          = $this->resolveArea($clientId);
        $exchangeRate  = $this->banxico->getCurrentUsdRate();
        $invoiceNumber = self::MESES_ES[$month] . '/' . $year;

        $comments = sprintf(
            '01 Nota de credito del Programa Forecast %d , %s%% de reembolso de las compras totales participantes facturadas durante %s/%d aplicado a las siguientes facturas:%s',
            $year,
            number_format($returnPercentage, 1),
            self::MESES_ES[$month],
            $year,
            implode(',', $folios)
        );

        return DB::transaction(function () use (
            $clientId, $year, $month, $sales, $returnPercentage, $currency, $folios, $groupId, $authUser, $files,
            $requestTypeId, $classificationId, $reasonId, $amount, $totalAmount, $area, $exchangeRate,
            $comments, $invoiceNumber
        ) {
            $reserved = $this->requestNumberService->reserveRequestNumber($requestTypeId, (int) $authUser->id);

            $request = $this->requestCrudService->createRequest([
                'requestNumber'    => $reserved['requestNumber'],
                'requestTypeId'    => $requestTypeId,
                'customerId'       => $clientId,
                'requestDate'      => now()->toDateString(),
                'currency'         => $currency,
                'area'             => $area,
                'exchangeRate'     => $exchangeRate,
                'reasonId'         => $reasonId,
                'classificationId' => $classificationId,
                'invoiceNumber'    => $invoiceNumber,
                'amount'           => $amount,
                'totalAmount'      => $totalAmount,
                'hasIva'           => true,
                'comments'         => $comments,
            ], $authUser);

            $this->requestAttachmentService->storeAndAttachFiles($request, $files, 'uploadSupport');

            $attributes = [
                'requestId'        => $request->id,
                'entityType'       => 'cliente',
                'entityId'         => (int) $clientId,
                'customerNumber'   => $clientId,
                'groupId'          => $groupId,
                'year'             => $year,
                'month'            => $month,
                'returnPercentage' => $returnPercentage,
                'currency'         => $currency,
                'salesAmount'      => $sales,
                'totalAmount'      => $totalAmount,
                'invoiceFolios'    => implode(',', $folios),
                'generatedBy'      => $authUser->id,
            ];

            $trashed = $this->findTrashedNote('cliente', $clientId, $year, $month);

            if ($trashed) {
                $trashed->restore();
                $trashed->fill($attributes)->save();

                return $trashed->load('request');
            }

            return ForecastCreditNote::create($attributes)->load('request');
        });
    }

    /** Área del cliente registrada en clientes_TME700618RC7_ext (misma fuente que autocompleta el campo al crear un request normal). */
    private function resolveArea(string $clientId): ?string
    {
        return DB::connection(self::INVOICES_CONNECTION)
            ->table(self::CLIENT_EXT_TABLE)
            ->where('idCliente', $clientId)
            ->value('area');
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
