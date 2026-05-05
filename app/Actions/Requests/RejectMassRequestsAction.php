<?php

namespace App\Actions\Requests;

use App\Services\RequestWorkflowService;

class RejectMassRequestsAction
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function execute(array $requestIds, mixed $authUser, bool $isAdmin, string $comments): array
    {
        return $this->requestWorkflowService->rejectMass($requestIds, $authUser, $isAdmin, $comments);
    }
}