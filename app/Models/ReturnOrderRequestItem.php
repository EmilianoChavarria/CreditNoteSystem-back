<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrderRequestItem extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'returnorderrequestitems';

    protected $fillable = [
        'returnOrderRequestId',
        'returnOrderItemId',
        'partNumber',
        'sapId',
        'replenishmentAccepted',
        'replenishmentReasonForRejection',
        'rejectedReplenishmentBy',
        'warehouseReceived',
        'warehouseAccepted',
        'warehouseReasonForRejection',
        'rejectedWarehouseBy',
    ];

    protected $casts = [
        'replenishmentAccepted'  => 'float',
        'rejectedReplenishmentBy'=> 'integer',
        'warehouseReceived'      => 'float',
        'warehouseAccepted'      => 'float',
        'rejectedWarehouseBy'    => 'integer',
        'createdAt'              => 'datetime',
        'updatedAt'              => 'datetime',
    ];

    public function returnOrderRequest()
    {
        return $this->belongsTo(ReturnOrderRequest::class, 'returnOrderRequestId');
    }

    public function returnOrderItem()
    {
        return $this->belongsTo(ReturnOrderItem::class, 'returnOrderItemId');
    }
}
