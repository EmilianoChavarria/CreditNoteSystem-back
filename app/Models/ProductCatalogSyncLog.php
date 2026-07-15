<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCatalogSyncLog extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $table = 'productcatalogsynclogs';

    protected $fillable = [
        'recordsSynced',
        'status',
        'errorMessage',
    ];

    protected $casts = [
        'recordsSynced' => 'integer',
    ];
}
