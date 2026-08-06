<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecastcomprobanteproductos', function (Blueprint $table) {
            $table->string('noPedido')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('forecastcomprobanteproductos', function (Blueprint $table) {
            $table->dropColumn('noPedido');
        });
    }
};
