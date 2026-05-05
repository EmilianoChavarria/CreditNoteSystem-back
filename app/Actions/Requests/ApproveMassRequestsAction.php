<?php

namespace App\Actions\Requests;

use App\Services\RequestWorkflowService;

class ApproveMassRequestsAction
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function execute(array $requestIds, mixed $authUser, bool $isAdmin, ?string $comments): array
    {
        return $this->requestWorkflowService->approveMass($requestIds, $authUser, $isAdmin, $comments);
    }
}