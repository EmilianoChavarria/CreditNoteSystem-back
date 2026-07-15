<?php

namespace App\Console\Commands;

use App\Models\Request as RequestModel;
use Illuminate\Console\Command;

class ReleaseStaleRequestNumberReservations extends Command
{
    protected $signature = 'requests:release-stale-reservations {--minutes=20 : Antigüedad mínima en minutos para liberar una reserva}';

    protected $description = 'Borra reservas de folio (reservedOnly=true) abandonadas hace más de N minutos, liberando el consecutivo para reuso';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        $staleReservations = RequestModel::query()
            ->where('reservedOnly', true)
            ->where('createdAt', '<', $cutoff)
            ->get(['id', 'requestNumber', 'requestTypeId', 'userId']);

        if ($staleReservations->isEmpty()) {
            $this->info('No hay reservas abandonadas para liberar.');

            return self::SUCCESS;
        }

        foreach ($staleReservations as $reservation) {
            $this->line("Liberando {$reservation->requestNumber} (draftId={$reservation->id}, userId={$reservation->userId})");
        }

        $count = RequestModel::query()
            ->whereIn('id', $staleReservations->pluck('id'))
            ->delete();

        $this->info("Liberadas {$count} reservas de folio.");

        return self::SUCCESS;
    }
}
