<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductClassification extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    public const RODAMIENTOS = 'Rodamientos';
    public const NO_RODAMIENTOS = 'No Rodamientos';

    protected $table = 'productclassifications';

    protected $fillable = [
        'idProducto',
        'clasificacion',
    ];
}
