<?php

namespace App\Console\Commands;

use App\Models\ProductCatalog;
use App\Models\ProductCatalogSyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProductCatalog extends Command
{
    protected $signature = 'products:sync-catalog';

    protected $description = 'Copy all product catalog records from external invoices DB to local snapshot table';

    private const CONNECTION    = 'invoices';
    private const PRODUCTS_TABLE = 'cproductos_TME700618RC7';
    private const LOG_RETENTION_DAYS = 10;
    private const CHUNK_SIZE    = 1000;

    /** Contador del corrido actual; se vuelca en el resumen final. */
    private int $productosSynced = 0;

    public function handle(): int
    {
        $startedAt = microtime(true);

        $this->info('['.Carbon::now()->format('Y-m-d H:i:s').'] Syncing product catalog...');
        Log::info('products:sync-catalog started');

        try {
            $count = $this->syncAll();

            ProductCatalogSyncLog::create([
                'recordsSynced' => $count,
                'status'        => 'success',
            ]);

            $this->pruneOldLogs();

            $this->summarize($startedAt, 'success');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            try {
                ProductCatalogSyncLog::create([
                    'recordsSynced' => 0,
                    'status'        => 'failed',
                    'errorMessage'  => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Log table unavailable — skip
            }

            $this->error("Sync failed: {$e->getMessage()}");

            $this->summarize($startedAt, 'failed', $e);

            return Command::FAILURE;
        }
    }

    /**
     * Resumen del corrido. Va tanto a la salida del command (que el scheduler redirige
     * a logs/sync-product-catalog.log vía appendOutputTo) como al log de Laravel, para
     * que quede rastro aunque se pierda el stdout del cron.
     */
    private function summarize(float $startedAt, string $status, ?Throwable $e = null): void
    {
        $context = [
            'status'       => $status,
            'productos'    => $this->productosSynced,
            'duration'     => $this->formatDuration(microtime(true) - $startedAt),
            'peakMemoryMb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ];

        if ($e !== null) {
            $context['error'] = $e->getMessage();
        }

        $this->newLine();
        $this->line('=== products:sync-catalog '.strtoupper($status).' @ '.Carbon::now()->format('Y-m-d H:i:s').' ===');
        $this->line("  Productos copiados: {$context['productos']}");
        $this->line("  Duración:           {$context['duration']}");
        $this->line("  Memoria pico:       {$context['peakMemoryMb']} MB");

        if ($e !== null) {
            $this->line("  Error:              {$e->getMessage()}");
        }

        if ($status === 'success') {
            Log::info('products:sync-catalog finished', $context);
        } else {
            Log::error('products:sync-catalog failed', $context);
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

    private function syncAll(): int
    {
        $now = Carbon::now();

        $this->productosSynced = 0;

        DB::connection(self::CONNECTION)
            ->table(self::PRODUCTS_TABLE)
            ->select([
                'idProducto', 'rfc', 'estatus', 'ClaveProdServ', 'ClaveUnidad',
                'unidadMedida', 'descripcion', 'esquemaImpuestos', 'valorUnitario',
                'Descuento', 'CuentaPredial', 'idUsuarioCc', 'ulActualizacionCc',
            ])
            ->orderBy('idProducto')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($now) {
                $records = $rows->map(fn ($r) => [
                    'idProducto'        => (string) $r->idProducto,
                    'rfc'               => (string) $r->rfc,
                    'estatus'           => (string) $r->estatus,
                    'claveProdServ'     => $r->ClaveProdServ,
                    'claveUnidad'       => $r->ClaveUnidad,
                    'unidadMedida'      => (string) $r->unidadMedida,
                    'descripcion'       => (string) $r->descripcion,
                    'esquemaImpuestos'  => $r->esquemaImpuestos,
                    'valorUnitario'     => (float) $r->valorUnitario,
                    'descuento'         => $r->Descuento !== null ? (float) $r->Descuento : null,
                    'cuentaPredial'     => $r->CuentaPredial,
                    'idUsuarioCc'       => (string) $r->idUsuarioCc,
                    'ulActualizacionCc' => $this->sanitizeDate($r->ulActualizacionCc),
                    'createdAt'         => $now,
                    'updatedAt'         => $now,
                ])->all();

                ProductCatalog::upsert(
                    $records,
                    ['idProducto'],
                    [
                        'rfc', 'estatus', 'claveProdServ', 'claveUnidad', 'unidadMedida',
                        'descripcion', 'esquemaImpuestos', 'valorUnitario', 'descuento',
                        'cuentaPredial', 'idUsuarioCc', 'ulActualizacionCc', 'updatedAt',
                    ]
                );

                $this->productosSynced += \count($records);
                $this->line("  Processed {$this->productosSynced} rows...");
            });

        return $this->productosSynced;
    }

    /**
     * La fuente trae "0000-00-00 00:00:00" en filas antiguas — fecha inválida
     * que MySQL en modo strict rechaza al insertar. Se normaliza a null.
     */
    private function sanitizeDate(mixed $value): ?string
    {
        if ($value === null || str_starts_with((string) $value, '0000-00-00')) {
            return null;
        }

        return (string) $value;
    }

    private function pruneOldLogs(): void
    {
        try {
            $cutoff  = Carbon::now()->subDays(self::LOG_RETENTION_DAYS);
            $deleted = ProductCatalogSyncLog::where('createdAt', '<', $cutoff)->delete();

            if ($deleted > 0) {
                $this->line("Pruned {$deleted} old log entries.");
            }
        } catch (Throwable) {
            // No DELETE privilege — pruning skipped silently
        }
    }
}
