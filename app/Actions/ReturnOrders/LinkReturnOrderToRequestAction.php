<?php

namespace App\Actions\ReturnOrders;

use App\Models\ReturnOrderRequest;
use App\Services\ReturnOrderRequestService;

class LinkReturnOrderToRequestAction
{
    public function __construct(
        private readonly ReturnOrderRequestService $returnOrderRequestService
    ) {
    }

    public function execute(int $returnOrderId, int $requestId, float $returnChargePercent): ReturnOrderRequest
    {
        return $this->returnOrderRequestService->linkToRequest($returnOrderId, $requestId, $returnChargePercent);
    }
}
