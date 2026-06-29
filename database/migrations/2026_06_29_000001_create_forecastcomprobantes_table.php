<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastcomprobantes', function (Blueprint $table) {
            $table->id();
            $table->string('receptorId', 50);
            $table->string('folio', 100)->default('');
            $table->string('serie', 20)->default('');
            $table->decimal('subTotal', 14, 2)->default(0);
            $table->decimal('iva', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->date('fechaEmision');
            $table->string('moneda', 3);
            $table->string('status', 30);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['receptorId', 'fechaEmision', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastcomprobantes');
    }
};
