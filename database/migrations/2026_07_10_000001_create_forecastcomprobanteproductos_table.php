<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastcomprobanteproductos', function (Blueprint $table) {
            $table->id();
            $table->string('receptorId', 50);
            $table->string('folio', 100)->default('');
            $table->unsignedInteger('conceptoIndex')->default(0);
            $table->string('claveProdServ', 20)->default('');
            $table->string('noIdentificacion', 100)->default('');
            $table->decimal('cantidad', 14, 4)->default(0);
            $table->string('claveUnidad', 20)->default('');
            $table->string('unidad', 50)->default('');
            $table->string('descripcion', 500)->default('');
            $table->decimal('valorUnitario', 14, 6)->default(0);
            $table->decimal('importe', 14, 2)->default(0);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['receptorId', 'folio', 'conceptoIndex'], 'forecastcomprobanteproductos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastcomprobanteproductos');
    }
};
