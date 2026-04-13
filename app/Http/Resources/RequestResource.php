<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requestNumber' => $this->requestNumber,
            'requestTypeId' => $this->requestTypeId,
            'userId' => $this->userId,
            'customerId' => $this->customerId,
            'status' => $this->status,
            'orderNumber' => $this->orderNumber,
            'requestDate' => $this->requestDate,
            'currency' => $this->currency,
            'area' => $this->area,
            'reasonId' => $this->reasonId,
            'classificationId' => $this->classificationId,
            'deliveryNote' => $this->deliveryNote,
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceDate' => $this->invoiceDate,
            'exchangeRate' => $this->exchangeRate,
            'creditNumber' => $this->creditNumber,
            'amount' => $this->amount,
            'hasIva' => $this->hasIva,
            'totalAmount' => $this->totalAmount,
            'comments' => $this->comments,
            'creditDebitRefId' => $this->creditDebitRefId,
            'newInvoice' => $this->newInvoice,
            'sapReturnOrder' => $this->sapReturnOrder,
            'hasRga' => $this->hasRga,
            'warehouseCode' => $this->warehouseCode,
            'replenishmentAmount' => $this->replenishmentAmount,
            'hasReplenishmentIva' => $this->hasReplenishmentIva,
            'replenishmentTotal' => $this->replenishmentTotal,
            'warehouseAmount' => $this->warehouseAmount,
            'hasWarehouseIva' => $this->hasWarehouseIva,
            'warehouseTotal' => $this->warehouseTotal,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'requestType' => $this->whenLoaded('requestType'),
            'user' => $this->whenLoaded('user'),
            'reason' => $this->whenLoaded('reason'),
            'classification' => $this->whenLoaded('classification'),
            'workflowCurrentStep' => $this->whenLoaded('workflowCurrentStep'),
        ];
    }
}