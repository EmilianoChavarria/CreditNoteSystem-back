<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchItem extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'batchItems';

    protected $fillable = [
        'batchId',
        'requestId',
        'userId',
        'status',
        'rowHash',
        'rawData',
        'errorLog',
        'processedAt',
    ];

    protected $casts = [
        'batchId' => 'integer',
        'requestId' => 'integer',
        'userId' => 'integer',
        'rawData' => 'array',
        'errorLog' => 'array',
        'processedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batchId');
    }

    public function request()
    {
        return $this->belongsTo(Request::class, 'requestId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
