<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastSale extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'forecastsales';

    protected $fillable = [
        'idClient',
        'year',
        'month',
        'amount',
    ];

    protected $casts = [
        'idClient' => 'integer',
        'year'     => 'integer',
        'month'    => 'integer',
        'amount'   => 'decimal:2',
    ];
}
