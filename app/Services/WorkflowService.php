<?php

namespace App\Services;

use App\Models\Workflow;

class WorkflowService
{
    public function create(array $data): Workflow
    {
        $workflow = Workflow::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'isActive' => $data['isActive'] ?? true,
            'requestTypeId' => $data['requestTypeId'],
            'classificationType' => $data['classificationType'] ?? null,
        ]);

        return $workflow->load(['requestType', 'classification']);
    }

    public function update(Workflow $workflow, array $data): Workflow
    {
        $workflow->update($data);

        return $workflow->load(['requestType', 'classification']);
    }
}