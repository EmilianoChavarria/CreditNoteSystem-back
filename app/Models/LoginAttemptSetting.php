<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttemptSetting extends Model
{
    use HasFactory;

    protected $table = 'loginattemptsettings';
    public $timestamps = false;

    protected $fillable = [
        'maxUserAttempts',
        'maxIpAttempts',
        'sessionTimeoutMinutes',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'maxUserAttempts' => 'integer',
        'maxIpAttempts' => 'integer',
        'sessionTimeoutMinutes' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}