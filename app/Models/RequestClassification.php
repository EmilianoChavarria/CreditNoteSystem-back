<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestClassification extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'requestClassification';
    protected $fillable = [
        'code',
        'name',
        'type'
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function requests()
    {
        return $this->hasMany(Request::class, 'classificationId');
    }

    public function requestTypes()
    {
        return $this->belongsToMany(
            RequestType::class,
            'classificationtypes',
            'classificationId',
            'typeRequestId'
        );
    }
}
