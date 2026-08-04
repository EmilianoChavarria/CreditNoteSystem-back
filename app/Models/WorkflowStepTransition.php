<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowStepTransition extends Model
{
    use HasFactory, SoftDeletes;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    protected $table = 'workflowsteptransitions';

    protected $fillable = [
        'workflowId',
        'fromStepId',
        'toStepId',
        'conditionField',
        'conditionOperator',
        'conditionValue',
        'priority',
        'markAsApproved',
        'deletedAt',
        'deletedBy',
    ];

    protected $casts = [
        'workflowId' => 'integer',
        'fromStepId' => 'integer',
        'toStepId' => 'integer',
        'priority' => 'integer',
        'markAsApproved' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
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
