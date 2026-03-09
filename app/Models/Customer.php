<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';
    protected $primaryKey = 'idCustomer';
    public $timestamps = false;

    protected $fillable = [
        'idClient',
        'area',
        'salesEngineerId',
        'salesManagerId',
        'financeManagerId',
        'marketingManagerId',
        'customerServiceManagerId',
    ];

    protected $casts = [
        'idCustomer' => 'integer',
        'idClient' => 'integer',
    ];



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
