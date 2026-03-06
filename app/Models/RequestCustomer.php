<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestCustomer extends Model
{
    use HasFactory;

    protected $table = 'requestscutomers';
    public $timestamps = false;

    protected $fillable = [
        'idRequest',
        'idCustomer',
        'salesEngineerId',
        'salesManagerId',
        'financeManagerId',
        'marketingManagerId',
        'customerServiceManagerId',
    ];

    protected $casts = [
        'idRequest' => 'integer',
        'idCustomer' => 'string',
        'salesEngineerId' => 'integer',
        'salesManagerId' => 'integer',
        'financeManagerId' => 'integer',
        'marketingManagerId' => 'integer',
        'customerServiceManagerId' => 'integer',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'idRequest');
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
