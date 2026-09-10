<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastannualtargets', function (Blueprint $table) {
            $table->id();
            // 'cliente' comparte el espacio de ids de forecastsales.idClient (clientes
            // nacionales y grupos); 'clienteExtranjero' apunta a distributors.id.
            $table->enum('targetType', ['cliente', 'clienteExtranjero']);
            $table->unsignedBigInteger('targetId');
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->unique(['targetType', 'targetId', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastannualtargets');
    }
};
