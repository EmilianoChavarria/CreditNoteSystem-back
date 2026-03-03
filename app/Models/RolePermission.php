<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasFactory;

    protected $table = 'rolespermission';
    public $timestamps = false;
    protected $fillable = [
        'requestTypeId',
        'roleId',
        'hasAccess',
    ];

    protected $casts = [
        'hasAccess' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function requesttype()
    {
        return $this->belongsTo(RequestType::class, 'requestTypeId');
    }
}
