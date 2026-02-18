<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'users';
    protected $fillable = [
        'fullName',
        'email',
        'passwordHash',
        'roleId',
        'supervisorId',
        'preferredLanguage',
        'isActive',
    ];

    protected $hidden = [
        'passwordHash',
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisorId');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'supervisorId');
    }

    public function requests()
    {
        return $this->hasMany(Request::class, 'userId');
    }

    public function security()
    {
        return $this->hasOne(UserSecurity::class, 'userId');
    }
}
