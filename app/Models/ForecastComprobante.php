<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastComprobante extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'forecastcomprobantes';

    protected $fillable = [
        'receptorId',
        'folio',
        'serie',
        'subTotal',
        'iva',
        'total',
        'fechaEmision',
        'moneda',
        'tipoCambio',
        'status',
    ];

    protected $casts = [
        'subTotal'     => 'decimal:2',
        'iva'          => 'decimal:2',
        'total'        => 'decimal:2',
        'tipoCambio'   => 'decimal:4',
        'fechaEmision' => 'datetime',
    ];
}
