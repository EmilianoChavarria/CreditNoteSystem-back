<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'countrycode'
    ];
}
