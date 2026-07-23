<?php

namespace App\Console\Commands;

use App\Models\ProductCatalog;
use App\Models\ProductCatalogSyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncProductCatalog extends Command
{
    protected $signature = 'products:sync-catalog';

    protected $description = 'Copy all product catalog records from external invoices DB to local snapshot table';

    private const CONNECTION    = 'invoices';
    private const PRODUCTS_TABLE = 'cproductos_TME700618RC7';
    private const LOG_RETENTION_DAYS = 10;
    private const CHUNK_SIZE    = 1000;

    public function handle(): int
    {
        $this->info('Syncing product catalog...');

        try {
            $count = $this->syncAll();

            ProductCatalogSyncLog::create([
                'recordsSynced' => $count,
                'status'        => 'success',
            ]);

            $this->info("Done. {$count} records synced.");

            $this->pruneOldLogs();

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

            return Command::FAILURE;
        }
    }

    private function syncAll(): int
    {
        $now   = Carbon::now();
        $total = 0;

        DB::connection(self::CONNECTION)
            ->table(self::PRODUCTS_TABLE)
            ->select([
                'idProducto', 'rfc', 'estatus', 'ClaveProdServ', 'ClaveUnidad',
                'unidadMedida', 'descripcion', 'esquemaImpuestos', 'valorUnitario',
                'Descuento', 'CuentaPredial', 'idUsuarioCc', 'ulActualizacionCc',
            ])
            ->orderBy('idProducto')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($now, &$total) {
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

                $total += \count($records);
                $this->line("  Processed {$total} rows...");
            });

        return $total;
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
