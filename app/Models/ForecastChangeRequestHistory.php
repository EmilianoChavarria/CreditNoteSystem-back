<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property int    $forecastChangeRequestId
 * @property string $action
 * @property int    $actorUserId
 * @property string $amount
 * @property string $step
 * @property Carbon $createdAt
 */
class ForecastChangeRequestHistory extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $table = 'forecastchangerequesthistories';

    protected $fillable = [
        'forecastChangeRequestId',
        'action',
        'actorUserId',
        'amount',
        'step',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actorUserId');
    }
}
