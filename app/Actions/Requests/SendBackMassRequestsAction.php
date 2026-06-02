<?php

namespace App\Actions\Requests;

use App\Services\RequestWorkflowService;

class SendBackMassRequestsAction
{
    public function __construct(
        private readonly RequestWorkflowService $requestWorkflowService
    ) {
    }

    public function execute(array $requestIds, int $targetWorkflowStepId, mixed $authUser, bool $isAdmin, ?string $comments): array
    {
        return $this->requestWorkflowService->sendBackMass($requestIds, $targetWorkflowStepId, $authUser, $isAdmin, $comments);
    }
}
