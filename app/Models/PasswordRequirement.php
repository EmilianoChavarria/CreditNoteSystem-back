<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordRequirement extends Model
{
    use HasFactory;

    protected $table = 'passwordRequirements';
    public $timestamps = false;

    protected $fillable = [
        'minLength',
        'requireUppercase',
        'requireLowercase',
        'requireNumbers',
        'requireSpecialCharacters',
        'expirationDays',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'requireUppercase' => 'boolean',
        'requireLowercase' => 'boolean',
        'requireNumbers' => 'boolean',
        'requireSpecialCharacters' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}
