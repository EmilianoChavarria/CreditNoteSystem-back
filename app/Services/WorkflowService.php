<?php

namespace App\Services;

use App\Models\Workflow;

class WorkflowService
{
    public function create(array $data): Workflow
    {
        $exists = Workflow::where('requestTypeId', $data['requestTypeId'])
            ->where('classificationType', $data['classificationType'] ?? null)
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException(
                'Ya existe un flujo con el mismo tipo de solicitud y clasificación.'
            );
        }

        $workflow = Workflow::create([
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