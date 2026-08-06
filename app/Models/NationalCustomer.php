<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NationalCustomer extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'national_customers';

    public const CURRENCY_USD = 'USD';
    public const CURRENCY_MXN = 'MXN';

    /** Monedas permitidas para el cliente nacional. */
    public const CURRENCIES = [self::CURRENCY_USD, self::CURRENCY_MXN];

    protected $fillable = [
        'customerNumber',
        'emails',
        'returnPercentage',
        'currency',
    ];

    protected $casts = [
        'returnPercentage' => 'float',
    ];
}
