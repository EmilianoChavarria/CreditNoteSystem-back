<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStepTransition extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'workflowStepTransitions';

    protected $fillable = [
        'workflowId',
        'fromStepId',
        'toStepId',
        'conditionField',
        'conditionOperator',
        'conditionValue',
        'priority',
    ];

    protected $casts = [
        'workflowId' => 'integer',
        'fromStepId' => 'integer',
        'toStepId' => 'integer',
        'priority' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflowId');
    }

    public function fromStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'fromStepId');
    }

    public function toStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'toStepId');
    }
}
