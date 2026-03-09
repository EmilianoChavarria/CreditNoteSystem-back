<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStepPermission extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'workflowStepPermissions';

    protected $fillable = [
        'workflowStepId',
        'roleId',
        'canApprove',
        'canReject',
        'canCancel',
        'canDelete',
    ];

    protected $casts = [
        'workflowStepId' => 'integer',
        'roleId' => 'integer',
        'canApprove' => 'boolean',
        'canReject' => 'boolean',
        'canCancel' => 'boolean',
        'canDelete' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'workflowStepId');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }
}
