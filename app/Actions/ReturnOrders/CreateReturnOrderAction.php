<?php

namespace App\Actions\ReturnOrders;

use App\Models\ReturnOrder;
use App\Services\ReturnOrderService;

class CreateReturnOrderAction
{
    public function __construct(
        private readonly ReturnOrderService $returnOrderService
    ) {
    }

    public function execute(int $clientId, ?int $userId, array $items, ?string $notes, int $chargeTypeId, ?float $customRate): ReturnOrder
    {
        return $this->returnOrderService->createReturnOrder($clientId, $userId, $items, $notes, $chargeTypeId, $customRate);
    }
}
