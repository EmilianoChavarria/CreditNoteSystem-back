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

    public function __construct(
        private readonly BanxicoService $banxico,
        private readonly FesaWsService $fesaWsService,
        private readonly XmlInvoiceService $xmlInvoiceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? Carbon::now()->year);

        $this->info("Syncing comprobantes for year {$year}...");

        try {
            $count = $this->syncYear($year);

            ForecastSyncLog::create([
                'year'          => $year,
                'recordsSynced' => $count,
                'status'        => 'success',
            ]);

            $this->info("Done. {$count} records synced.");

            $this->pruneOldLogs();

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

            return Command::FAILURE;
        }
    }

    private function syncYear(int $year): int
    {
        $now         = Carbon::now();
        $startOfYear = Carbon::create($year)->startOfYear();
        $endOfYear   = Carbon::create($year)->endOfYear();
        $total       = 0;

        // Single Banxico call for the entire year — map [Y-m-d => rate]
        $this->line("  Fetching Banxico FIX rates for {$year}...");
        $rates = $this->banxico->getRatesByDateRange(
            $startOfYear->format('Y-m-d'),
            min($endOfYear, $now)->format('Y-m-d')
        );

        DB::connection(self::CONNECTION)
            ->table(self::COMPROBANTES_TABLE)
            ->where('serie', '')
            ->whereBetween('fechaEmision', [$startOfYear, $endOfYear])
            ->select(['receptorId', 'folio', 'serie', 'subTotal', 'iva', 'total', 'fechaEmision', 'moneda', 'status'])
            ->orderBy('receptorId')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($now, $rates, &$total) {
                $records = $rows->map(function ($r) use ($now, $rates) {
                    $date      = Carbon::parse($r->fechaEmision)->format('Y-m-d');
                    $tipoCambio = $rates[$date] ?? null;

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
                        'createdAt'    => $now,
                        'updatedAt'    => $now,
                    ];
                })->all();

                // Upsert on (receptorId, folio) — no DELETE needed
                // Captures status changes (e.g. Emitido → Cancelado) on next sync
                ForecastComprobante::upsert(
                    $records,
                    ['receptorId', 'folio'],
                    ['subTotal', 'iva', 'total', 'fechaEmision', 'moneda', 'tipoCambio', 'status', 'updatedAt']
                );

                $total += \count($records);
                $this->line("  Processed {$total} rows...");

                $this->syncProductsForRecords($records);
            });

        return $total;
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
                ['claveProdServ', 'noIdentificacion', 'cantidad', 'claveUnidad', 'unidad', 'descripcion', 'valorUnitario', 'importe', 'updatedAt']
            );
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
