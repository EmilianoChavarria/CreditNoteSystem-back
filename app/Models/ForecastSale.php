<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastSale extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'forecast_sales';

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
