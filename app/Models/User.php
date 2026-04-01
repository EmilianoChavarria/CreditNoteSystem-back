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
        'clientId'
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

    public function systemNotifications()
    {
        return $this->hasMany(Notification::class, 'userId');
    }

    public function security()
    {
        return $this->hasOne(UserSecurity::class, 'userId');
    }

    public function leaderAssignments()
    {
        return $this->hasMany(UserAssignment::class, 'leaderUserId');
    }

    public function assignedByLeaders()
    {
        return $this->hasMany(UserAssignment::class, 'assignedUserId');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'userAssignments', 'leaderUserId', 'assignedUserId')
            ->withPivot(['id', 'isActive', 'createdAt', 'updatedAt'])
            ->withTimestamps('createdAt', 'updatedAt');
    }

    public function leaders()
    {
        return $this->belongsToMany(User::class, 'userAssignments', 'assignedUserId', 'leaderUserId')
            ->withPivot(['id', 'isActive', 'createdAt', 'updatedAt'])
            ->withTimestamps('createdAt', 'updatedAt');
    }
}
