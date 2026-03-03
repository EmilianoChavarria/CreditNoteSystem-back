<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    use HasFactory;

    protected $table = 'blockedIps';
    public $timestamps = false;

    protected $fillable = [
        'ipAddress',
        'failedAttempts',
        'isBlockedPermanently',
        'blockedAt',
        'releasedAt',
    ];

    protected $casts = [
        'isBlockedPermanently' => 'boolean',
        'blockedAt' => 'datetime',
        'releasedAt' => 'datetime',
    ];
}
