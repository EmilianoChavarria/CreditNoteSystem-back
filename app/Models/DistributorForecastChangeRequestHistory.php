<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorForecastChangeRequestHistory extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $table = 'distributorforecastchangerequesthistories';

    protected $fillable = [
        'distributorForecastChangeRequestId',
        'action',
        'actorUserId',
        'forecast',
        'step',
    ];

    protected $casts = [
        'distributorForecastChangeRequestId' => 'integer',
        'actorUserId'                        => 'integer',
        'forecast'                           => 'integer',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actorUserId');
    }
}
