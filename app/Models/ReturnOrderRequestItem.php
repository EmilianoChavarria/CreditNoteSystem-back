<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrderRequestItem extends Model
{
    use SoftDeletes;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

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

    /**
     * true si el revisor (replenishment/almacén) ya capturó alguna cantidad para este producto.
     */
    public function hasReviewData(): bool
    {
        return $this->replenishmentAccepted !== null
            || $this->warehouseReceived !== null
            || $this->warehouseAccepted !== null;
    }
}
