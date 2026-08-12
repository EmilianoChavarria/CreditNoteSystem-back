<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_credit_notes', function (Blueprint $table) {
            // Moneda en la que se calculó la nota (la del cliente). Las notas previas
            // a este cambio se generaron todas en USD, que es el default de la columna.
            $table->string('currency', 3)->default('USD')->after('returnPercentage');
        });
    }

    public function down(): void
    {
        Schema::table('forecast_credit_notes', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
