<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    public $timestamps = false;

    protected $fillable = [
        'roleName',
        'color',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'roleId');
    }

    public function permissions()
    {
        return $this->hasMany(RolePermission::class, 'roleId');
    }
}
