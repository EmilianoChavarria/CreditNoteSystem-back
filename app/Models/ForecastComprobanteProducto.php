<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastComprobanteProducto extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'forecastcomprobanteproductos';

    protected $fillable = [
        'receptorId',
        'folio',
        'conceptoIndex',
        'claveProdServ',
        'noIdentificacion',
        'cantidad',
        'claveUnidad',
        'unidad',
        'descripcion',
        'valorUnitario',
        'importe',
    ];

    protected $casts = [
        'conceptoIndex' => 'integer',
        'cantidad'      => 'decimal:4',
        'valorUnitario' => 'decimal:6',
        'importe'       => 'decimal:2',
    ];
}
