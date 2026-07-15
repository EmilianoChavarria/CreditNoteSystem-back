<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productcatalog', function (Blueprint $table) {
            $table->id();
            $table->string('idProducto', 50);
            $table->string('rfc', 13)->default('');
            $table->string('estatus', 20)->default('');
            $table->string('claveProdServ', 20)->nullable();
            $table->string('claveUnidad', 20)->nullable();
            $table->string('unidadMedida', 100)->default('');
            $table->string('descripcion', 500)->default('');
            $table->text('esquemaImpuestos')->nullable();
            $table->decimal('valorUnitario', 21, 6)->default(0);
            $table->decimal('descuento', 21, 6)->nullable();
            $table->string('cuentaPredial', 100)->nullable();
            $table->string('idUsuarioCc', 50)->default('');
            $table->timestamp('ulActualizacionCc')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique('idProducto', 'productcatalog_idproducto_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productcatalog');
    }
};
