<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorForecast extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'distributorforecasts';

    protected $fillable = [
        'distributorId',
        'year',
        'month',
        'forecast',
        'sales',
    ];

    protected $casts = [
        'distributorId' => 'integer',
        'year'          => 'integer',
        'month'         => 'integer',
        'forecast'      => 'integer',
        'sales'         => 'integer',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'distributorId');
    }
}
