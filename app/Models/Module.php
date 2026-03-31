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
        'name',
        'parentid',
        'url',
        'icon',
        'orderindex',
        'requiredactionid',
    ];

    protected $casts = [
        'parentid' => 'integer',
        'orderindex' => 'integer',
        'requiredactionid' => 'integer',
    ];

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class, 'moduleId');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'moduleid');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parentid');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parentid')->orderBy('orderindex')->orderBy('id');
    }

    public function requiredAction()
    {
        return $this->belongsTo(Action::class, 'requiredactionid');
    }
}
