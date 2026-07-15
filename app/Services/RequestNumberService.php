<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\DB;

class RequestNumberService
{
    /**
     * Prefijos para cada tipo de solicitud
     * Mapeo de requestTypeId a prefijo de número consecutivo
     */
    private const REQUEST_TYPE_PREFIXES = [
        1 => 'C',      // Credits
        2 => 'D',      // Debits
        3 => 'AC',     // Auditor Credits
        4 => 'AD',     // Auditor Debits
        5 => 'R',      // Re-invoicing
        6 => 'DM',      // Material Return
    ];

    /**
     * Reserva el siguiente número de solicitud creando un draft atómicamente.
     * Usa lockForUpdate sobre el request_type para serializar generación concurrente.
     *
     * @return array{requestNumber: string, draftId: int}
     */
    public function reserveRequestNumber(int $requestTypeId, int $userId): array
    {
        return DB::transaction(function () use ($requestTypeId, $userId) {
            // Bloquear la fila del tipo para serializar generación concurrente del mismo tipo
            DB::table('requesttype')->where('id', $requestTypeId)->lockForUpdate()->first();

            $requestNumber = $this->computeNextNumber($requestTypeId);

            $draft = RequestModel::create([
                'requestTypeId' => $requestTypeId,
                'requestNumber' => $requestNumber,
                'userId'        => $userId,
                'status'        => 'draft',
                'reservedOnly'  => true,
            ]);

            return ['requestNumber' => $requestNumber, 'draftId' => $draft->id];
        });
    }

    /**
     * Libera una reserva de número no utilizada (usuario abandonó el formulario
     * sin llenar ningún dato). Soft-delete en vez de borrado físico: el usuario
     * de BD de la app no tiene permiso DELETE. Además reescribe requestNumber
     * agregando el id de la fila (vía CONCAT en el propio UPDATE) porque la
     * columna tiene UNIQUE KEY — dejar "C000003" tal cual en una fila liberada
     * bloquearía para siempre que ese folio se vuelva a asignar. Solo aplica a
     * filas que siguen siendo pura reserva (reservedOnly=true, no liberadas
     * antes) y pertenecen al usuario; computeNextNumber() ignora estas filas
     * liberadas al calcular el siguiente consecutivo.
     */
    public function releaseReservation(int $draftId, int $userId): bool
    {
        return (bool) RequestModel::query()
            ->where('id', $draftId)
            ->where('userId', $userId)
            ->where('reservedOnly', true)
            ->whereNull('deletedAt')
            ->update([
                'requestNumber' => DB::raw("CONCAT(requestNumber, '-REL-', id)"),
                'deletedAt' => now(),
                'deletedBy' => $userId,
            ]);
    }

    /**
     * Generar el siguiente número de solicitud para un tipo específico (sin reservar).
     * Usar solo cuando no hay riesgo de concurrencia (reportes, seeds, etc.).
     *
     * @param int $requestTypeId Identificador del tipo de solicitud
     * @return string Número formateado (ej: CR000001, DB000002, etc.)
     */
    public function generateRequestNumber(int $requestTypeId): string
    {
        return $this->computeNextNumber($requestTypeId);
    }

    /**
     * Calcula el siguiente consecutivo a partir del MAX(sufijo numérico) real
     * de todos los folios existentes de ese tipo (en vez de "la última fila
     * insertada"), para que liberar reservas de en medio no rompa el
     * consecutivo ni deje huecos permanentes. Las reservas ya liberadas
     * (reservedOnly=true + deletedAt seteado por releaseReservation()) se
     * excluyen para que su folio pueda reutilizarse.
     */
    private function computeNextNumber(int $requestTypeId): string
    {
        $prefix = self::REQUEST_TYPE_PREFIXES[$requestTypeId] ?? 'REQ';
        $prefixLength = strlen($prefix);

        $maxSequence = (int) RequestModel::where('requestTypeId', $requestTypeId)
            ->where('requestNumber', 'like', $prefix . '%')
            ->where(function ($query) {
                $query->where('reservedOnly', false)
                    ->orWhereNull('deletedAt');
            })
            ->selectRaw('MAX(CAST(SUBSTRING(requestNumber, ?) AS UNSIGNED)) as maxSequence', [$prefixLength + 1])
            ->value('maxSequence');

        return $prefix . str_pad((string) ($maxSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener el prefijo para un tipo de solicitud
     * 
     * @param int $requestTypeId
     * @return string
     */
    public function getPrefixForType(int $requestTypeId): string
    {
        return self::REQUEST_TYPE_PREFIXES[$requestTypeId] ?? 'REQ';
    }

    /**
     * Obtener todos los prefijos disponibles
     * 
     * @return array
     */
    public function getAllPrefixes(): array
    {
        return self::REQUEST_TYPE_PREFIXES;
    }
}
