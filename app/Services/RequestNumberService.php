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
     * Generar el siguiente número de solicitud para un tipo específico
     * 
     * @param int $requestTypeId Identificador del tipo de solicitud
     * @return string Número formateado (ej: CR00001, DB00002, etc.)
     */
    public function generateRequestNumber(int $requestTypeId): string
    {
        $prefix = self::REQUEST_TYPE_PREFIXES[$requestTypeId] ?? 'REQ';

        // Obtener el último número para este tipo
        $lastRequest = RequestModel::where('requestTypeId', $requestTypeId)
            ->whereNotNull('requestNumber')
            ->orderByDesc('id')
            ->select('requestNumber')
            ->first();

        $nextSequence = 1;

        if ($lastRequest && $lastRequest->requestNumber) {
            // Extraer el número de la cadena (ej: "CR00001" -> 1)
            $number = (int) substr((string) $lastRequest->requestNumber, strlen($prefix));
            $nextSequence = $number + 1;
        }

        // Formatear con 5 dígitos (00001, 00002, etc.)
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
