<?php

namespace App\Console\Commands;

use App\Models\Request as RequestModel;
use Illuminate\Console\Command;

class BackfillReservedOnlyDrafts extends Command
{
    protected $signature = 'requests:backfill-reserved-only {--apply : Sin este flag solo se listan candidatos, no se modifica nada}';

    protected $description = 'Identifica drafts históricos que son pura reserva de folio nunca usada (creados antes de existir reservedOnly) y los marca reservedOnly=true para que el job de limpieza los libere';

    /**
     * Campos que un usuario real habría llenado al guardar el draft de verdad.
     * Si TODOS están en su valor "intacto", la fila nunca se tocó desde que
     * RequestNumberService::reserveRequestNumber() la creó.
     */
    private const UNTOUCHED_NULL_FIELDS = [
        'customerId', 'orderNumber', 'requestDate', 'currency', 'area',
        'reasonId', 'classificationId', 'deliveryNote', 'invoiceNumber',
        'invoiceDate', 'exchangeRate', 'creditNumber', 'amount', 'comments',
        'totalAmount', 'creditDebitRefId', 'newInvoice', 'sapReturnOrder',
        'warehouseCode', 'replenishmentAmount', 'replenishmentTotal',
        'warehouseAmount', 'warehouseTotal',
    ];

    private const UNTOUCHED_FALSE_FIELDS = [
        'hasIva', 'hasRga', 'hasReplenishmentIva', 'hasWarehouseIva',
    ];

    public function handle(): int
    {
        $query = RequestModel::query()
            ->where('status', 'draft')
            ->where('reservedOnly', false)
            ->whereDoesntHave('workflowCurrentStep')
            ->whereDoesntHave('attachments');

        foreach (self::UNTOUCHED_NULL_FIELDS as $field) {
            $query->whereNull($field);
        }

        foreach (self::UNTOUCHED_FALSE_FIELDS as $field) {
            $query->where(function ($q) use ($field) {
                $q->whereNull($field)->orWhere($field, false);
            });
        }

        $candidates = $query->get(['id', 'requestNumber', 'requestTypeId', 'userId', 'createdAt']);

        if ($candidates->isEmpty()) {
            $this->info('No se encontraron drafts huérfanos históricos.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'requestNumber', 'requestTypeId', 'userId', 'createdAt'],
            $candidates->map(fn ($r) => [$r->id, $r->requestNumber, $r->requestTypeId, $r->userId, $r->createdAt])
        );

        $this->info("Encontrados {$candidates->count()} drafts huérfanos históricos.");

        if (!$this->option('apply')) {
            $this->comment('Modo listado. Vuelve a correr con --apply para marcarlos reservedOnly=true.');

            return self::SUCCESS;
        }

        $updated = RequestModel::query()
            ->whereIn('id', $candidates->pluck('id'))
            ->update(['reservedOnly' => true]);

        $this->info("Marcados {$updated} drafts como reservedOnly=true.");
        $this->comment('Corre ahora "php artisan requests:release-stale-reservations --minutes=0" para liberarlos y reusar sus folios.');

        return self::SUCCESS;
    }
}
