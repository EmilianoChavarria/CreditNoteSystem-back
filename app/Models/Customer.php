<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'customers';
    protected $fillable = [
        'customerNumber',
        'customerName',
        'area',
        'salesEngineerId',
        'salesManagerId',
        'financeManagerId',
        'marketingManagerId',
        'customerServiceManagerId',
        'isActive',
    ];

    protected $casts = [
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    public function requests()
    {
        return $this->hasMany(Request::class, 'customerId');
    }

    public function salesEngineer()
    {
        return $this->belongsTo(User::class, 'salesEngineerId');
    }

    public function salesManager()
    {
        return $this->belongsTo(User::class, 'salesManagerId');
    }

    public function financeManager()
    {
        return $this->belongsTo(User::class, 'financeManagerId');
    }

    public function marketingManager()
    {
        return $this->belongsTo(User::class, 'marketingManagerId');
    }

    public function customerServiceManager()
    {
        return $this->belongsTo(User::class, 'customerServiceManagerId');
    }
}
