<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastSyncLog extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'forecastsynclogs';

    protected $fillable = [
        'year',
        'recordsSynced',
        'status',
        'errorMessage',
    ];

    protected $casts = [
        'year'          => 'integer',
        'recordsSynced' => 'integer',
    ];
}
