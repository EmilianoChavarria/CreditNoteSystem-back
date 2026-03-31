<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';
    public $timestamps = false;

    protected $fillable = [
        'roleid',
        'moduleid',
        'actionid',
        'isallowed',
    ];

    protected $casts = [
        'roleid' => 'integer',
        'moduleid' => 'integer',
        'actionid' => 'integer',
        'isallowed' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleid');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'moduleid');
    }

    public function action()
    {
        return $this->belongsTo(Action::class, 'actionid');
    }
}
