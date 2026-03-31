<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowRequestStep extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'workflowRequestSteps';

    protected $fillable = [
        'requestId',
        'workflowStepId',
        'assignedRoleId',
        'assignedUserId',
        'status',
        'startedAt',
        'completedAt',
    ];

    protected $casts = [
        'requestId' => 'integer',
        'workflowStepId' => 'integer',
        'assignedRoleId' => 'integer',
        'assignedUserId' => 'integer',
        'startedAt' => 'datetime',
        'completedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'requestId');
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

    public function history()
    {
        return $this->hasMany(WorkflowRequestHistory::class, 'requestWorkflowStepId');
    }
}
