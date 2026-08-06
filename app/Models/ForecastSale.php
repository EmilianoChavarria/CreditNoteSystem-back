<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForecastSale extends Model
{
    use SoftDeletes;

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
    public const DELETED_AT = 'deletedAt';

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
