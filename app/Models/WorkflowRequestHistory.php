<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowRequestHistory extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $table = 'workflowRequestHistory';
    public $timestamps = false;

    protected $fillable = [
        'requestWorkflowStepId',
        'requestId',
        'workflowStepId',
        'actionUserId',
        'actionType',
        'comments',
    ];

    protected $casts = [
        'requestWorkflowStepId' => 'integer',
        'requestId' => 'integer',
        'workflowStepId' => 'integer',
        'actionUserId' => 'integer',
        'createdAt' => 'datetime',
    ];

    public function requestStep()
    {
        return $this->belongsTo(WorkflowRequestStep::class, 'requestWorkflowStepId');
    }

    public function request()
    {
        return $this->belongsTo(Request::class, 'requestId');
    }

    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'workflowStepId');
    }

    public function actionUser()
    {
        return $this->belongsTo(User::class, 'actionUserId');
    }
}
