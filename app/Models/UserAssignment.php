<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAssignment extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'userAssignments';

    protected $fillable = [
        'leaderUserId',
        'assignedUserId',
        'isActive',
    ];

    protected $casts = [
        'leaderUserId' => 'integer',
        'assignedUserId' => 'integer',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leaderUserId');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assignedUserId');
    }
}
