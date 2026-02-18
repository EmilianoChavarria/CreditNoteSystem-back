<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $table = 'modules';
    public $timestamps = false;

    protected $fillable = [
        'moduleName',
    ];

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class, 'moduleId');
    }
}
