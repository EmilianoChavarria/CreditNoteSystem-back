<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargePolicy extends Model
{
    use HasFactory, SoftDeletes;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    protected $table = 'chargePolicies';

    protected $fillable = [
        'day',
        'percentage',
    ];

    protected $casts = [
        'day'        => 'integer',
        'percentage' => 'float',
        'createdAt'  => 'datetime',
        'updatedAt'  => 'datetime',
        'deletedAt'  => 'datetime',
    ];
}
