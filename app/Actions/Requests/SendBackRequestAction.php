<?php

namespace App\Actions\Requests;

use App\Services\RequestWorkflowService;

class SendBackRequestAction
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function execute(int $requestId, int $targetWorkflowStepId, mixed $authUser, bool $isAdmin, string $comments): array
    {
        return $this->requestWorkflowService->sendBack($requestId, $targetWorkflowStepId, $authUser, $isAdmin, $comments);
    }
}
