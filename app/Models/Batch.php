<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $table = 'batches';
    public $timestamps = false;

    protected $fillable = [
        'userId',
        'fileName',
        'batchType',
        'minRange',
        'maxRange',
        'totalRecords',
        'processedRecords',
        'processingRecords',
        'errorRecords',
        'status',
    ];

    protected $casts = [
        'userId' => 'integer',
        'minRange' => 'integer',
        'maxRange' => 'integer',
        'totalRecords' => 'integer',
        'processedRecords' => 'integer',
        'processingRecords' => 'integer',
        'errorRecords' => 'integer',
        'createdAt' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(BatchItem::class, 'batchId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
