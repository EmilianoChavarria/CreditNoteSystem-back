<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returnOrderRequestItems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('returnOrderRequestId');
            $table->unsignedBigInteger('returnOrderItemId');

            // Campos de solo lectura (pre-poblados del returnOrderItem)
            $table->string('partNumber', 100)->nullable();
            $table->string('sapId', 50)->nullable();

            // Campos que llena el revisor — Replenishment
            $table->decimal('replenishmentAccepted', 15, 6)->nullable();
            $table->string('replenishmentReasonForRejection', 255)->nullable();

            // Campos que llena el revisor — Warehouse
            $table->decimal('warehouseReceived', 15, 6)->nullable();
            $table->decimal('warehouseAccepted', 15, 6)->nullable();
            $table->string('warehouseReasonForRejection', 255)->nullable();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('returnOrderRequestId')
                ->references('id')
                ->on('returnOrderRequests')
                ->onDelete('cascade');

            $table->foreign('returnOrderItemId')
                ->references('id')
                ->on('returnOrderItems')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returnOrderRequestItems');
    }
};
