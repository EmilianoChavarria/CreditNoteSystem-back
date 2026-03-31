<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestTypePermission extends Model
{
    use HasFactory;

    protected $table = 'requesttypepermissions';
    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'request_type_id',
        'action_id',
        'is_allowed',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'request_type_id' => 'integer',
        'action_id' => 'integer',
        'is_allowed' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function requestType()
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
