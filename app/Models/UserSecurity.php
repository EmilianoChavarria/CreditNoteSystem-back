<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSecurity extends Model
{
    use HasFactory;

    protected $table = 'usersecurity';
    protected $primaryKey = 'userId';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'userId',
        'sessionToken',
        'lastActivityAt',
        'lastKnownIp',
        'lockedUntil',
        'failedAttempts',
        'lastFailedAt',
        'lastLoginAt',
    ];

    protected $casts = [
        'lastActivityAt' => 'datetime',
        'lockedUntil' => 'datetime',
        'lastFailedAt' => 'datetime',
        'lastLoginAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
