<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'workflows';

    protected $fillable = [
        'name',
        'description',
        'isActive',
        'requestTypeId',
        'classificationType',
        'deletedAt',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function requestType()
    {
        return $this->belongsTo(RequestType::class, 'requestTypeId');
    }

    public function classification()
    {
        return $this->belongsTo(RequestClassification::class, 'classificationId');
    }
}
