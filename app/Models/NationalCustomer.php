<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NationalCustomer extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'national_customers';

    protected $fillable = [
        'customerNumber',
        'emails',
        'returnPercentage',
    ];

    protected $casts = [
        'returnPercentage' => 'float',
    ];
}
