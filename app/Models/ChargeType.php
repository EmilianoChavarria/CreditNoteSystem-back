<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeType extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'chargeTypes';

    protected $fillable = [
        'name',
        'label',
        'percentage',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'createdAt'  => 'datetime',
        'updatedAt'  => 'datetime',
    ];

    public function returnOrders()
    {
        return $this->hasMany(ReturnOrder::class, 'chargeTypeId');
    }
}
