<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrderRequestItem extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'returnOrderRequestItems';

    protected $fillable = [
        'returnOrderRequestId',
        'returnOrderItemId',
        'partNumber',
        'sapId',
        'replenishmentAccepted',
        'replenishmentReasonForRejection',
        'warehouseReceived',
        'warehouseAccepted',
        'warehouseReasonForRejection',
    ];

    protected $casts = [
        'replenishmentAccepted' => 'float',
        'warehouseReceived'     => 'float',
        'warehouseAccepted'     => 'float',
        'createdAt'             => 'datetime',
        'updatedAt'             => 'datetime',
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
