<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory;

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'requests';
    protected $fillable = [
        'requestNumber',
        'requestTypeId',
        'userId',
        'customerId',
        'status',
        'orderNumber',
        'requestDate',
        'currency',
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
        'comments',
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
        'deletedAt' => 'datetime',
    ];

    public function requestType()
    {
        return $this->belongsTo(RequestType::class, 'requestTypeId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function reason()
    {
        return $this->belongsTo(RequestReason::class, 'reasonId');
    }

    public function classification()
    {
        return $this->belongsTo(RequestClassification::class, 'classificationId');
    }

    public function workflowCurrentStep()
    {
        return $this->hasOne(WorkflowRequestCurrentStep::class, 'requestId');
    }

    public function workflowSteps()
    {
        return $this->hasMany(WorkflowRequestStep::class, 'requestId');
    }

    public function workflowHistory()
    {
        return $this->hasMany(WorkflowRequestHistory::class, 'requestId');
    }

    public function returnOrderRequest()
    {
        return $this->hasOne(ReturnOrderRequest::class, 'requestId');
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class, 'requestId')->where('isActive', true);
    }
}
