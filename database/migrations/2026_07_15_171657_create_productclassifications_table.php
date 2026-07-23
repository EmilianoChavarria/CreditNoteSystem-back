<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productclassifications', function (Blueprint $table) {
            $table->id();
            $table->string('idProducto', 50);
            $table->enum('clasificacion', ['Rodamientos', 'No Rodamientos']);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique('idProducto', 'productclassifications_idproducto_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productclassifications');
    }
};
