<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastCreditNote extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $table = 'forecast_credit_notes';

    protected $fillable = [
        'requestId',
        'entityType',
        'entityId',
        'customerNumber',
        'groupId',
        'year',
        'month',
        'returnPercentage',
        'salesAmount',
        'totalAmount',
        'invoiceFolios',
        'generatedBy',
    ];

    protected $casts = [
        'returnPercentage' => 'float',
        'salesAmount'      => 'float',
        'totalAmount'      => 'float',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'requestId');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class, 'groupId');
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generatedBy');
    }
}
