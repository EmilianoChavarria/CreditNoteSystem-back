<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    protected $table = 'requesttype';
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function requests()
    {
        return $this->hasMany(Request::class, 'requestTypeId');
    }

    public function classifications()
    {
        return $this->belongsToMany(
            RequestClassification::class,
            'classificationtypes',
            'typeRequestId',
            'classificationId'
        );
    }

    public function permissions()
    {
        return $this->hasMany(RequestTypePermission::class, 'request_type_id');
    }
}
