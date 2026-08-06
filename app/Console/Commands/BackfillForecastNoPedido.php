<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillForecastNoPedido extends Command
{
    protected $signature = 'forecast:backfill-no-pedido
                            {--dry-run : Muestra cuántas filas se actualizarían sin escribir nada}';

    protected $description = 'Llena noPedido en forecastcomprobanteproductos a partir de la descripcion ya guardada';

    private const TABLE      = 'forecastcomprobanteproductos';
    private const CHUNK_SIZE = 5000;

    /**
     * El PO viene como 3er segmento de la descripcion ("PARTE^PARTE-DESC^NOPEDIDO^REMISION^...").
     * Mismo índice que usan XmlInvoiceService::extractNoPedido() e InvoicePdfService::parseDescripcion().
     */
    private const PO_EXPR = "SUBSTRING_INDEX(SUBSTRING_INDEX(descripcion, '^', 3), '^', -1)";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Las filas sincronizadas antes de que existiera la columna quedaron en NULL.
        $pending = DB::table(self::TABLE)->whereNull('noPedido')->count();

        if ($pending === 0) {
            $this->info('No hay filas pendientes: noPedido ya está poblado.');

            return Command::SUCCESS;
        }

        $this->info("Filas con noPedido NULL: {$pending}");

        // Sin los 3 separadores la extracción devolvería basura (la descripcion completa).
        $malformed = DB::table(self::TABLE)
            ->whereNull('noPedido')
            ->whereRaw("CHAR_LENGTH(descripcion) - CHAR_LENGTH(REPLACE(descripcion, '^', '')) < 3")
            ->count();

        if ($malformed > 0) {
            $this->warn("  {$malformed} filas no traen el formato esperado y se omiten.");
        }

        if ($dryRun) {
            $devoluciones = DB::table(self::TABLE)
                ->whereNull('noPedido')
                ->whereRaw("UPPER(TRIM(" . self::PO_EXPR . ")) LIKE 'DM%'")
                ->count();

            $this->info('Dry run: se actualizarían ' . ($pending - $malformed) . " filas ({$devoluciones} con PO de devolución).");

            return Command::SUCCESS;
        }

        try {
            $total = $this->backfill();
        } catch (Throwable $e) {
            $this->error("Backfill falló: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $devoluciones = DB::table(self::TABLE)
            ->whereRaw("UPPER(TRIM(noPedido)) LIKE 'DM%'")
            ->distinct()
            ->count(DB::raw("CONCAT(receptorId, '|', folio)"));

        $this->info("Listo. {$total} filas actualizadas.");
        $this->info("Comprobantes con PO de devolución (DM): {$devoluciones}");

        return Command::SUCCESS;
    }

    private function backfill(): int
    {
        $total = 0;

        do {
            $updated = DB::table(self::TABLE)
                ->whereNull('noPedido')
                ->whereRaw("CHAR_LENGTH(descripcion) - CHAR_LENGTH(REPLACE(descripcion, '^', '')) >= 3")
                ->limit(self::CHUNK_SIZE)
                ->update(['noPedido' => DB::raw('TRIM(' . self::PO_EXPR . ')')]);

            $total += $updated;

            if ($updated > 0) {
                $this->line("  Actualizadas {$total} filas...");
            }
        } while ($updated > 0);

        return $total;
    }
}
