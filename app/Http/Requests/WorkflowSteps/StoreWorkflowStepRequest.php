<?php

namespace App\Http\Requests\WorkflowSteps;

use App\Models\Role;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workflowTable = (new Workflow())->getTable();
        $roleTable = (new Role())->getTable();
        $stepTable = (new WorkflowStep())->getTable();

        return [
            'workflowId' => ['required', 'integer', 'exists:' . $workflowTable . ',id'],
            'stepName' => ['required', 'string', 'max:255'],
            'stepOrder' => ['required', 'integer', 'min:1'],
            'roleId' => ['required', 'integer', 'exists:' . $roleTable . ',id'],
            'isInitialStep' => ['nullable', 'boolean'],
            'isFinalStep' => ['nullable', 'boolean'],
            'transitions' => ['sometimes', 'array'],
            'transitions.*.toStepId' => ['required', 'integer', 'exists:' . $stepTable . ',id'],
            'transitions.*.conditionField' => ['nullable', 'string', 'max:100'],
            'transitions.*.conditionOperator' => ['nullable', 'string', 'max:20'],
            'transitions.*.conditionValue' => ['nullable', 'string', 'max:100'],
            'transitions.*.priority' => ['nullable', 'integer', 'min:1'],
        ];
    }
}