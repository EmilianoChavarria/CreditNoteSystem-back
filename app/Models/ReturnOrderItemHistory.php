<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrderItemHistory extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'returnOrderItemHistory';

    protected $fillable = [
        'invoiceFolio',
        'invoiceClientId',
        'conceptoIndex',
        'returnOrderItemId',
        'returnedQuantity',
    ];

    protected $casts = [
        'returnedQuantity' => 'float',
        'createdAt'        => 'datetime',
        'updatedAt'        => 'datetime',
    ];

    public function returnOrderItem()
    {
        return $this->belongsTo(ReturnOrderItem::class, 'returnOrderItemId');
    }
}
