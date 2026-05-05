<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrderRequest extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'returnOrderRequests';

    protected $fillable = [
        'returnOrderId',
        'requestId',
        'returnChargePercent',
    ];

    protected $casts = [
        'returnChargePercent' => 'float',
        'createdAt'           => 'datetime',
        'updatedAt'           => 'datetime',
    ];

    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class, 'returnOrderId');
    }

    public function request()
    {
        return $this->belongsTo(Request::class, 'requestId');
    }

    public function items()
    {
        return $this->hasMany(ReturnOrderRequestItem::class, 'returnOrderRequestId');
    }
}
