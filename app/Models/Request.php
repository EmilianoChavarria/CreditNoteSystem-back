<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'requests';
    protected $fillable = [
        'requestNumber',
        'requestTypeId',
        'userId',
        'status',
        'orderNumber',
        'requestDate',
        'currency',
        'customerId',
        'area',
        'reasonId',
        'classificationId',
        'deliveryNote',
        'invoiceNumber',
        'invoiceDate',
        'exchangeRate',
        'creditNumber',
        'amount',
        'hasIva',
        'totalAmount',
        'creditDebitRefId',
        'newInvoice',
        'sapReturnOrder',
        'hasRga',
        'warehouseCode',
        'replenishmentAmount',
        'hasReplenishmentIva',
        'replenishmentTotal',
        'warehouseAmount',
        'hasWarehouseIva',
        'warehouseTotal',
    ];

    protected $casts = [
        'requestDate' => 'date',
        'invoiceDate' => 'date',
        'hasIva' => 'boolean',
        'hasRga' => 'boolean',
        'hasReplenishmentIva' => 'boolean',
        'hasWarehouseIva' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function requestType()
    {
        return $this->belongsTo(RequestType::class, 'requestTypeId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }

    public function reason()
    {
        return $this->belongsTo(RequestReason::class, 'reasonId');
    }

    public function classification()
    {
        return $this->belongsTo(RequestClassification::class, 'classificationId');
    }
}
