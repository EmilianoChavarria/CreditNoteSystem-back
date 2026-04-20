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
            $table->string('partNumber')->nullable();
            $table->string('sapId')->nullable();
            $table->decimal('replenishmentAccepted', 15, 4)->nullable();
            $table->text('replenishmentReasonForRejection')->nullable();
            $table->decimal('warehouseReceived', 15, 4)->nullable();
            $table->decimal('warehouseAccepted', 15, 4)->nullable();
            $table->text('warehouseReasonForRejection')->nullable();
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
