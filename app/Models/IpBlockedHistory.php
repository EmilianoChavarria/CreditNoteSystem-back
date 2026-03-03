<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpBlockedHistory extends Model
{
    use HasFactory;

    protected $table = 'ipBlockedHistory';
    public $timestamps = false;

    protected $fillable = [
        'ipAddress',
        'action',
        'reason',
        'userId',
        'adminUserId',
        'createdAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
    ];
}
