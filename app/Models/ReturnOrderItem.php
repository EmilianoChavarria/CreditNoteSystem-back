<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnOrderItem extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'returnOrderItems';

    protected $fillable = [
        'returnOrderId',
        'invoiceFolio',
        'invoiceClientId',
        'conceptoIndex',
        'claveProdServ',
        'descripcion',
        'claveUnidad',
        'unidad',
        'valorUnitario',
        'originalQuantity',
        'requestedQuantity',
    ];

    protected $casts = [
        'valorUnitario'     => 'float',
        'originalQuantity'  => 'float',
        'requestedQuantity' => 'float',
        'createdAt'         => 'datetime',
        'updatedAt'         => 'datetime',
    ];

    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class, 'returnOrderId');
    }

    public function history()
    {
        return $this->hasMany(ReturnOrderItemHistory::class, 'returnOrderItemId');
    }
}
