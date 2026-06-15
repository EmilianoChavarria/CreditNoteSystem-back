<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property int         $idClient
 * @property int         $year
 * @property int         $month
 * @property string      $previousAmount
 * @property string      $proposedAmount
 * @property string      $status
 * @property string      $currentStep
 * @property int         $approverUserId
 * @property int         $submittedByUserId
 * @property Carbon      $createdAt
 * @property Carbon      $updatedAt
 */
class ForecastChangeRequest extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'forecastchangerequests';

    protected $fillable = [
        'idClient',
        'year',
        'month',
        'previousAmount',
        'proposedAmount',
        'status',
        'currentStep',
        'approverUserId',
        'submittedByUserId',
    ];

    protected $casts = [
        'idClient'       => 'integer',
        'year'           => 'integer',
        'month'          => 'integer',
        'previousAmount' => 'decimal:2',
        'proposedAmount' => 'decimal:2',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(ForecastChangeRequestHistory::class, 'forecastChangeRequestId')
            ->orderBy('createdAt');
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
