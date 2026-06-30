<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecastcomprobantes', function (Blueprint $table) {
            $table->decimal('tipoCambio', 10, 4)->nullable()->after('moneda');
        });
    }

    public function down(): void
    {
        Schema::table('forecastcomprobantes', function (Blueprint $table) {
            $table->dropColumn('tipoCambio');
        });
    }
};
