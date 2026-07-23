<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributorForecastChangeRequest extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'distributorforecastchangerequests';

    protected $fillable = [
        'distributorId',
        'year',
        'month',
        'previousForecast',
        'proposedForecast',
        'status',
        'currentStep',
        'approverUserId',
        'submittedByUserId',
    ];

    protected $casts = [
        'distributorId'     => 'integer',
        'year'              => 'integer',
        'month'             => 'integer',
        'previousForecast'  => 'integer',
        'proposedForecast'  => 'integer',
        'approverUserId'    => 'integer',
        'submittedByUserId' => 'integer',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(DistributorForecastChangeRequestHistory::class, 'distributorForecastChangeRequestId')
            ->orderBy('createdAt');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributorId');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approverUserId');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submittedByUserId');
    }
}
