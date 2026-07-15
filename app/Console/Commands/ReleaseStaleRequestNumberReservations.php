<?php

namespace App\Console\Commands;

use App\Models\Request as RequestModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseStaleRequestNumberReservations extends Command
{
    protected $signature = 'requests:release-stale-reservations {--minutes=20 : Antigüedad mínima en minutos para liberar una reserva}';

    protected $description = 'Libera (soft-delete) reservas de folio (reservedOnly=true) abandonadas hace más de N minutos, liberando el consecutivo para reuso';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        $staleReservations = RequestModel::query()
            ->where('reservedOnly', true)
            ->whereNull('deletedAt')
            ->where('createdAt', '<', $cutoff)
            ->get(['id', 'requestNumber', 'requestTypeId', 'userId']);

        if ($staleReservations->isEmpty()) {
            $this->info('No hay reservas abandonadas para liberar.');

            return self::SUCCESS;
        }

        foreach ($staleReservations as $reservation) {
            $this->line("Liberando {$reservation->requestNumber} (draftId={$reservation->id}, userId={$reservation->userId})");
        }

        // Soft-delete (no DELETE): el usuario de BD de la app no tiene permiso
        // DELETE en producción. También reescribe requestNumber (CONCAT + id)
        // porque la columna es UNIQUE — sin esto el folio quedaría bloqueado
        // para siempre aunque la fila esté "eliminada".
        $count = RequestModel::query()
            ->whereIn('id', $staleReservations->pluck('id'))
            ->update([
                'requestNumber' => DB::raw("CONCAT(requestNumber, '-REL-', id)"),
                'deletedAt' => now(),
                'deletedBy' => null,
            ]);

        $this->info("Liberadas {$count} reservas de folio.");

        return self::SUCCESS;
    }
}
