<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrder extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'returnOrders';

    protected $fillable = [
        'clientId',
        'userId',
        'status',
        'notes',
        'charge',
        'chargePolicyId',
    ];

    protected $casts = [
        'createdAt'      => 'datetime',
        'updatedAt'      => 'datetime',
        'charge'         => 'boolean',
        'chargePolicyId' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(ReturnOrderItem::class, 'returnOrderId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function chargePolicy()
    {
        return $this->belongsTo(ChargePolicy::class, 'chargePolicyId');
    }
}
