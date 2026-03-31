<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowStep extends Model
{
    use HasFactory, SoftDeletes;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    protected $table = 'workflowSteps';

    protected $fillable = [
        'workflowId',
        'stepName',
        'stepOrder',
        'roleId',
        'isInitialStep',
        'isFinalStep',
        'deletedAt',
    ];

    protected $casts = [
        'workflowId' => 'integer',
        'stepOrder' => 'integer',
        'roleId' => 'integer',
        'isInitialStep' => 'boolean',
        'isFinalStep' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflowId');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function permissions()
    {
        return $this->hasMany(WorkflowStepPermission::class, 'workflowStepId');
    }

    public function requestSteps()
    {
        return $this->hasMany(WorkflowRequestStep::class, 'workflowStepId');
    }

    public function requestHistory()
    {
        return $this->hasMany(WorkflowRequestHistory::class, 'workflowStepId');
    }

    public function currentRequestSteps()
    {
        return $this->hasMany(WorkflowRequestCurrentStep::class, 'workflowStepId');
    }

    public function outgoingTransitions()
    {
        return $this->hasMany(WorkflowStepTransition::class, 'fromStepId');
    }

    public function incomingTransitions()
    {
        return $this->hasMany(WorkflowStepTransition::class, 'toStepId');
    }
}
