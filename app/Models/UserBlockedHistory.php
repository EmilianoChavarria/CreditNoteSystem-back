<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBlockedHistory extends Model
{
    use HasFactory;

    protected $table = 'userBlockedHistory';
    public $timestamps = false;

    protected $fillable = [
        'userId',
        'action',
        'reason',
        'ipAddress',
        'adminUserId',
        'createdAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
    ];
}
