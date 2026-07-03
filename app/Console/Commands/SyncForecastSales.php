<?php

namespace App\Console\Commands;

use App\Models\ForecastComprobante;
use App\Models\ForecastSyncLog;
use App\Services\BanxicoService;
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

    public function __construct(private readonly BanxicoService $banxico)
    {
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
            });

        return $total;
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
