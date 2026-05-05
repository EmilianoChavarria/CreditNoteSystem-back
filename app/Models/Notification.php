<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'notifications';

    protected $fillable = [
        'userId',
        'type',
        'relatedId',
        'title',
        'message',
        'isRead',
        'readAt',
    ];

    protected $casts = [
        'userId' => 'integer',
        'relatedId' => 'integer',
        'isRead' => 'boolean',
        'readAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}