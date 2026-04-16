<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returnOrderItems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('returnOrderId');
            $table->string('invoiceFolio', 50);
            $table->unsignedInteger('invoiceClientId');
            $table->unsignedInteger('conceptoIndex');
            $table->string('claveProdServ', 20);
            $table->text('descripcion');
            $table->string('claveUnidad', 10);
            $table->string('unidad', 50)->nullable();
            $table->decimal('valorUnitario', 15, 6);
            $table->decimal('originalQuantity', 15, 6);
            $table->decimal('requestedQuantity', 15, 6);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('returnOrderId')
                ->references('id')
                ->on('returnOrders')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returnOrderItems');
    }
};
