<?php

namespace App\Actions\Requests;

use App\Services\RequestWorkflowService;

class CancelRequestAction
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function execute(int $requestId, mixed $authUser, bool $isAdmin, ?string $comments): array
    {
        return $this->requestWorkflowService->cancel($requestId, $authUser, $isAdmin, $comments);
    }
}
