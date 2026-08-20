<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ProductClassification extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    public const RODAMIENTOS = 'Rodamientos';
    public const NO_RODAMIENTOS = 'No Rodamientos';
    public const UNCLASSIFIED = 'unclassified';

    protected $table = 'productclassifications';

    protected $fillable = [
        'idProducto',
        'clasificacion',
    ];

    /**
     * Filtra un query de productcatalog por clasificacion.
     * Valores aceptados: '', 'all', 'unclassified', 'Rodamientos', 'No Rodamientos'.
     * El match se hace sobre el idProducto trimeado porque el catalogo origen
     * puede traer espacios sueltos (mismo criterio que ProductCatalogController::getAll).
     */
    public static function applyFilter(Builder $query, string $clasificacion): void
    {
        $clasificacion = trim($clasificacion);

        if ($clasificacion === '' || strtolower($clasificacion) === 'all') {
            return;
        }

        $catalogTable = $query->getModel()->getTable();
        $matchColumn = DB::getTablePrefix() . $catalogTable . '.idProducto';

        if (strtolower($clasificacion) === self::UNCLASSIFIED) {
            $query->whereNotExists(function (QueryBuilder $sub) use ($matchColumn) {
                $sub->select(DB::raw(1))
                    ->from((new self())->getTable() . ' as pc')
                    ->whereRaw('pc.idProducto = TRIM(' . $matchColumn . ')');
            });

            return;
        }

        $query->whereExists(function (QueryBuilder $sub) use ($matchColumn, $clasificacion) {
            $sub->select(DB::raw(1))
                ->from((new self())->getTable() . ' as pc')
                ->whereRaw('pc.idProducto = TRIM(' . $matchColumn . ')')
                ->where('pc.clasificacion', $clasificacion);
        });
    }
}
