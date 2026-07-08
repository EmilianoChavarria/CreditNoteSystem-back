<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrderItem extends Model
{
    use SoftDeletes;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';

    protected $table = 'returnorderitems';

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
        'deletedBy',
    ];

    protected $casts = [
        'valorUnitario'     => 'float',
        'originalQuantity'  => 'float',
        'requestedQuantity' => 'float',
        'createdAt'         => 'datetime',
        'updatedAt'         => 'datetime',
        'deletedAt'         => 'datetime',
        'deletedBy'         => 'integer',
    ];

    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class, 'returnOrderId');
    }

    public function history()
    {
        return $this->hasMany(ReturnOrderItemHistory::class, 'returnOrderItemId');
    }

    public function requestItem()
    {
        return $this->hasOne(ReturnOrderRequestItem::class, 'returnOrderItemId');
    }
}
