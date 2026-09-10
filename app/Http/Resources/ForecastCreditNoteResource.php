<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastCreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'requestId'        => $this->requestId,
            'requestNumber'    => $this->whenLoaded('request', fn () => $this->request?->requestNumber),
            'requestStatus'    => $this->whenLoaded('request', fn () => $this->request?->status),
            'entityType'       => $this->entityType,
            'entityId'         => $this->entityId,
            // Resueltos en el historial global (ForecastCreditNoteService::getScopedHistory()).
            'clientName'       => $this->clientName ?? null,
            'groupId'          => $this->groupId,
            'groupName'        => $this->groupName ?? null,
            'customerNumber'   => $this->customerNumber,
            'year'             => $this->year,
            'month'            => $this->month,
            'returnPercentage' => $this->returnPercentage,
            'currency'         => $this->currency,
            'salesAmount'      => $this->salesAmount,
            'totalAmount'      => $this->totalAmount,
            'invoiceFolios'    => $this->invoiceFolios,
            'generatedBy'      => $this->generatedBy,
            'createdAt'        => $this->createdAt,
        ];
    }
}
