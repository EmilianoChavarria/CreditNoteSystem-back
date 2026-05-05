<?php

namespace App\Actions\Requests;

use App\Services\RequestWorkflowService;

class RejectRequestAction
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function execute(int $requestId, mixed $authUser, bool $isAdmin, string $comments): array
    {
        return $this->requestWorkflowService->reject($requestId, $authUser, $isAdmin, $comments);
    }
}