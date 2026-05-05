<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowRequestCurrentStep extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'workflowRequestCurrentStep';

    protected $fillable = [
        'requestId',
        'workflowId',
        'workflowStepId',
        'assignedRoleId',
        'assignedUserId',
        'status',
    ];

    protected $casts = [
        'requestId' => 'integer',
        'workflowId' => 'integer',
        'workflowStepId' => 'integer',
        'assignedRoleId' => 'integer',
        'assignedUserId' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'requestId');
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflowId');
    }

    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'workflowStepId');
    }

    public function assignedRole()
    {
        return $this->belongsTo(Role::class, 'assignedRoleId');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assignedUserId');
    }
}
