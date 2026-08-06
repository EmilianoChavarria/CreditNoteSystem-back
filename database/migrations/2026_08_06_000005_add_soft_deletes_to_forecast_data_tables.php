<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Al dar de baja a un cliente del padrón de forecast, su información se borra
     * lógicamente: se conserva para auditoría y, si lo reactivan, arranca en blanco.
     */
    private const TABLES = [
        'forecastsales',
        'forecastchangerequests',
        'forecast_credit_notes',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes('deletedAt');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes('deletedAt');
            });
        }
    }
};
