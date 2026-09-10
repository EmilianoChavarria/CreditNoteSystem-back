<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Objetivo anual (techo) de forecast. La suma de los 12 meses de un cliente,
 * grupo o distribuidor no puede rebasarlo.
 */
class ForecastAnnualTarget extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    /** Clientes nacionales y grupos: mismo espacio de ids que forecastsales.idClient. */
    public const TYPE_CLIENT = 'cliente';

    /** Clientes extranjeros: distributors.id. */
    public const TYPE_DISTRIBUTOR = 'clienteExtranjero';

    public const TYPES = [self::TYPE_CLIENT, self::TYPE_DISTRIBUTOR];

    protected $table = 'forecastannualtargets';

    protected $fillable = [
        'targetType',
        'targetId',
        'year',
        'amount',
    ];

    protected $casts = [
        'targetId' => 'integer',
        'year'     => 'integer',
        'amount'   => 'float',
    ];
}
