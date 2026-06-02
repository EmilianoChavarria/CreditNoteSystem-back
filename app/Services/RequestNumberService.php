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
            ]);

            return ['requestNumber' => $requestNumber, 'draftId' => $draft->id];
        });
    }

    /**
     * Generar el siguiente número de solicitud para un tipo específico (sin reservar).
     * Usar solo cuando no hay riesgo de concurrencia (reportes, seeds, etc.).
     *
     * @param int $requestTypeId Identificador del tipo de solicitud
     * @return string Número formateado (ej: CR00001, DB00002, etc.)
     */
    public function generateRequestNumber(int $requestTypeId): string
    {
        return $this->computeNextNumber($requestTypeId);
    }

    private function computeNextNumber(int $requestTypeId): string
    {
        $prefix = self::REQUEST_TYPE_PREFIXES[$requestTypeId] ?? 'REQ';

        $lastRequest = RequestModel::where('requestTypeId', $requestTypeId)
            ->whereNotNull('requestNumber')
            ->orderByDesc('id')
            ->select('requestNumber')
            ->first();

        $nextSequence = 1;

        if ($lastRequest && $lastRequest->requestNumber) {
            $number = (int) substr((string) $lastRequest->requestNumber, strlen($prefix));
            $nextSequence = $number + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
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
