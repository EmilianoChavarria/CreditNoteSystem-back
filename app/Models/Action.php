<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;

    protected $table = 'actions';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'actionid');
    }

    public function requiredByModules()
    {
        return $this->hasMany(Module::class, 'requiredactionid');
    }

    public function requestTypePermissions()
    {
        return $this->hasMany(RequestTypePermission::class, 'action_id');
    }
}
