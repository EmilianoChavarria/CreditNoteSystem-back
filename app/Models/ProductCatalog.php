<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCatalog extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'productcatalog';

    protected $fillable = [
        'idProducto',
        'rfc',
        'estatus',
        'claveProdServ',
        'claveUnidad',
        'unidadMedida',
        'descripcion',
        'esquemaImpuestos',
        'valorUnitario',
        'descuento',
        'cuentaPredial',
        'idUsuarioCc',
        'ulActualizacionCc',
    ];

    protected $casts = [
        'valorUnitario'     => 'decimal:6',
        'descuento'         => 'decimal:6',
        'ulActualizacionCc' => 'datetime',
    ];

    public function classification()
    {
        return $this->hasOne(ProductClassification::class, 'idProducto', 'idProducto');
    }
}
