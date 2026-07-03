<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecastcomprobantes', function (Blueprint $table) {
            $table->unique(['receptorId', 'folio'], 'forecastcomprobantes_receptor_folio_unique');
        });
    }

    public function down(): void
    {
        Schema::table('forecastcomprobantes', function (Blueprint $table) {
            $table->dropUnique('forecastcomprobantes_receptor_folio_unique');
        });
    }
};
