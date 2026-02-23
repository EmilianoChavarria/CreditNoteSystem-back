<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestAttachment extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $table = 'requestattachments';
    public $timestamps = false;

    protected $fillable = [
        'requestId',
        'fileName',
        'fileSize',
        'filePath',
        'fileExtension',
    ];

    protected $casts = [
        'requestId' => 'integer',
        'fileSize' => 'integer',
        'createdAt' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'requestId');
    }
}
