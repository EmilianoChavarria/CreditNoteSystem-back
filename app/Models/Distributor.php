<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distributor extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'distributors';

    protected $fillable = [
        'businessName',
        'taxId',
        'address',
        'emails',
        'clientNumber',
        'countrycode',
        'salesEngineerId',
        'salesManagerId',
    ];

    public function forecasts(): HasMany
    {
        return $this->hasMany(DistributorForecast::class, 'distributorId');
    }

    public function salesEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesEngineerId');
    }

    public function salesManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesManagerId');
    }
}
