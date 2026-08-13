<?php

namespace App\Console\Commands;

use App\Models\ForecastComprobante;
use App\Models\ForecastComprobanteProducto;
use App\Models\ForecastSyncLog;
use App\Services\BanxicoService;
use App\Services\FesaWsService;
use App\Services\XmlInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncForecastSales extends Command
{
    protected $signature = 'forecast:sync-sales
                            {--year= : Year to sync (default: current year)}';

    protected $description = 'Copy comprobantes from external invoices DB to local snapshot table';

    private const CONNECTION         = 'invoices';
    private const COMPROBANTES_TABLE = 'comprobantes_TME700618RC7';
    private const LOG_RETENTION_DAYS = 10;
    private const CHUNK_SIZE         = 500;

    /** Días hacia atrás que se buscan para hallar el FIX aplicable a un comprobante. */
    private const RATE_LOOKBACK_DAYS = 10;

    /** Contadores del corrido actual; se vuelcan en el resumen final. */
    private int $comprobantesSynced = 0;
    private int $productosSynced    = 0;
    private int $xmlFailures        = 0;

    public function __construct(
        private readonly BanxicoService $banxico,
        private readonly FesaWsService $fesaWsService,
        private readonly XmlInvoiceService $xmlInvoiceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year      = (int) ($this->option('year') ?? Carbon::now()->year);
        $startedAt = microtime(true);

        $this->info('['.Carbon::now()->format('Y-m-d H:i:s')."] Syncing comprobantes for year {$year}...");
        Log::info('forecast:sync-sales started', ['year' => $year]);

        try {
            $count = $this->syncYear($year);

            ForecastSyncLog::create([
                'year'          => $year,
                'recordsSynced' => $count,
                'status'        => 'success',
            ]);

            $this->pruneOldLogs();

            $this->summarize($year, $startedAt, 'success');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            try {
                ForecastSyncLog::create([
                    'year'          => $year,
                    'recordsSynced' => 0,
                    'status'        => 'failed',
                    'errorMessage'  => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Log table unavailable — skip
            }

            $this->error("Sync failed: {$e->getMessage()}");

            $this->summarize($year, $startedAt, 'failed', $e);

            return Command::FAILURE;
        }
    }

    /**
     * Resumen del corrido. Va tanto a la salida del command (que el scheduler redirige
     * a logs/sync-forecast.log vía appendOutputTo) como al log de Laravel, para que
     * quede rastro aunque se pierda el stdout del cron.
     */
    private function summarize(int $year, float $startedAt, string $status, ?Throwable $e = null): void
    {
        $context = [
            'year'         => $year,
            'status'       => $status,
            'comprobantes' => $this->comprobantesSynced,
            'productos'    => $this->productosSynced,
            'xmlFailures'  => $this->xmlFailures,
            'duration'     => $this->formatDuration(microtime(true) - $startedAt),
            'peakMemoryMb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ];

        if ($e !== null) {
            $context['error'] = $e->getMessage();
        }

        $this->newLine();
        $this->line('=== forecast:sync-sales '.strtoupper($status).' @ '.Carbon::now()->format('Y-m-d H:i:s').' ===');
        $this->line("  Año:                   {$year}");
        $this->line("  Comprobantes copiados: {$context['comprobantes']}");
        $this->line("  Productos copiados:    {$context['productos']}");
        $this->line("  XML fallidos:          {$context['xmlFailures']}");
        $this->line("  Duración:              {$context['duration']}");
        $this->line("  Memoria pico:          {$context['peakMemoryMb']} MB");

        if ($e !== null) {
            $this->line("  Error:                 {$e->getMessage()}");
        }

        if ($status === 'success') {
            Log::info('forecast:sync-sales finished', $context);
        } else {
            Log::error('forecast:sync-sales failed', $context);
        }
    }

    private function formatDuration(float $seconds): string
    {
        $minutes = (int) floor($seconds / 60);

        if ($minutes === 0) {
            return \sprintf('%.1fs', $seconds);
        }

        return \sprintf('%dm %ds', $minutes, (int) round($seconds - $minutes * 60));
    }

    private function syncYear(int $year): int
    {
        $now         = Carbon::now();
        $startOfYear = Carbon::create($year)->startOfYear();
        $endOfYear   = Carbon::create($year)->endOfYear();

        $this->comprobantesSynced = 0;

        // Single Banxico call for the entire year — map [Y-m-d => rate].
        // Se piden días extra antes del 1 de enero porque cada comprobante se liga
        // al FIX del día hábil anterior (ver resolveRate).
        $this->line("  Fetching Banxico FIX rates for {$year}...");
        $rates = $this->banxico->getRatesByDateRange(
            $startOfYear->copy()->subDays(self::RATE_LOOKBACK_DAYS)->format('Y-m-d'),
            min($endOfYear, $now)->format('Y-m-d')
        );

        DB::connection(self::CONNECTION)
            ->table(self::COMPROBANTES_TABLE)
            ->where('serie', '')
            ->whereBetween('fechaEmision', [$startOfYear, $endOfYear])
            ->select(['receptorId', 'folio', 'serie', 'subTotal', 'iva', 'total', 'fechaEmision', 'moneda', 'status', 'tipoComprobante'])
            ->orderBy('receptorId')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($now, $rates) {
                $records = $rows->map(function ($r) use ($now, $rates) {
                    $tipoCambio = $this->resolveRate($rates, Carbon::parse($r->fechaEmision));

                    return [
                        'receptorId'   => (string) $r->receptorId,
                        'folio'        => (string) ($r->folio ?? ''),
                        'serie'        => (string) ($r->serie ?? ''),
                        'subTotal'     => (float) $r->subTotal,
                        'iva'          => (float) $r->iva,
                        'total'        => (float) $r->total,
                        'fechaEmision' => $r->fechaEmision,
                        'moneda'       => (string) $r->moneda,
                        'tipoCambio'   => $tipoCambio,
                        'status'       => (string) $r->status,
                        'tipoComprobante' => (string) ($r->tipoComprobante ?? ''),
                        'createdAt'    => $now,
                        'updatedAt'    => $now,
                    ];
                })->all();

                // Upsert on (receptorId, folio)
                // Captures status changes (e.g. Emitido → Cancelado) on next sync
                ForecastComprobante::upsert(
                    $records,
                    ['receptorId', 'folio'],
                    ['subTotal', 'iva', 'total', 'fechaEmision', 'moneda', 'tipoCambio', 'status', 'tipoComprobante', 'updatedAt']
                );

                $this->comprobantesSynced += \count($records);
                $this->line("  Processed {$this->comprobantesSynced} rows...");

                $this->syncProductsForRecords($records);
            });

        return $this->comprobantesSynced;
    }

    /**
     * FIX aplicable a un comprobante emitido en $fecha.
     *
     * Banxico fecha la serie SF43718 por día de determinación, pero el tipo de cambio
     * se publica en el DOF al día hábil siguiente: una factura del día D se timbra con
     * el FIX determinado en D-1. Se verificó contra 2,447 facturas en USD — 2,322 usan
     * el del día hábil anterior y ninguna el del mismo día.
     *
     * @param array<string, float> $rates
     */
    private function resolveRate(array $rates, Carbon $fecha): ?float
    {
        for ($i = 1; $i <= self::RATE_LOOKBACK_DAYS; $i++) {
            $key = $fecha->copy()->subDays($i)->format('Y-m-d');

            if (isset($rates[$key])) {
                return $rates[$key];
            }
        }

        return null;
    }

    /**
     * Para cada comprobante del chunk aún sin productos guardados, abre su XML
     * (mismo proceso que usa el módulo de invoices/devoluciones vía FESA) y
     * guarda sus conceptos en forecastcomprobanteproductos.
     */
    private function syncProductsForRecords(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $receptorIds = array_values(array_unique(array_column($records, 'receptorId')));
        $folios      = array_values(array_unique(array_column($records, 'folio')));

        $existing = ForecastComprobanteProducto::query()
            ->whereIn('receptorId', $receptorIds)
            ->whereIn('folio', $folios)
            ->get(['receptorId', 'folio'])
            ->map(fn ($p) => "{$p->receptorId}|{$p->folio}")
            ->flip();

        $now = Carbon::now();

        foreach ($records as $r) {
            if ($r['folio'] === '' || isset($existing["{$r['receptorId']}|{$r['folio']}"])) {
                continue;
            }

            try {
                $xmlContent = $this->fesaWsService->fetchXmlString($r['folio']);
                $conceptos  = $this->xmlInvoiceService->getConceptosFromXmlString($xmlContent);
            } catch (Throwable $e) {
                $this->xmlFailures++;
                $this->warn("  No se pudo obtener XML de folio {$r['folio']} (receptor {$r['receptorId']}): {$e->getMessage()}");

                continue;
            }

            if (empty($conceptos)) {
                continue;
            }

            $rows = array_map(static fn (array $c) => [
                'receptorId'       => $r['receptorId'],
                'folio'            => $r['folio'],
                'conceptoIndex'    => $c['conceptoIndex'],
                'claveProdServ'    => $c['claveProdServ'],
                'noIdentificacion' => $c['noIdentificacion'],
                'noPedido'         => $c['noPedido'],
                'cantidad'         => $c['cantidad'],
                'claveUnidad'      => $c['claveUnidad'],
                'unidad'           => $c['unidad'],
                'descripcion'      => $c['descripcion'],
                'valorUnitario'    => $c['valorUnitario'],
                'importe'          => $c['importe'],
                'createdAt'        => $now,
                'updatedAt'        => $now,
            ], $conceptos);

            ForecastComprobanteProducto::upsert(
                $rows,
                ['receptorId', 'folio', 'conceptoIndex'],
                ['claveProdServ', 'noIdentificacion', 'noPedido', 'cantidad', 'claveUnidad', 'unidad', 'descripcion', 'valorUnitario', 'importe', 'updatedAt']
            );

            $this->productosSynced += \count($rows);
        }
    }

    private function pruneOldLogs(): void
    {
        try {
            $cutoff = Carbon::now()->subDays(self::LOG_RETENTION_DAYS);
            $deleted = ForecastSyncLog::where('createdAt', '<', $cutoff)->delete();

            if ($deleted > 0) {
                $this->line("Pruned {$deleted} old log entries.");
            }
        } catch (Throwable) {
            // No DELETE privilege — pruning skipped silently
        }
    }
}
