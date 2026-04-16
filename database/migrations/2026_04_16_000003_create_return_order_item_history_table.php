<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returnOrderItemHistory', function (Blueprint $table) {
            $table->id();
            $table->string('invoiceFolio', 50);
            $table->unsignedInteger('invoiceClientId');
            $table->unsignedInteger('conceptoIndex');
            $table->unsignedBigInteger('returnOrderItemId');
            $table->decimal('returnedQuantity', 15, 6);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('returnOrderItemId')
                ->references('id')
                ->on('returnOrderItems')
                ->onDelete('cascade');

            $table->index(['invoiceFolio', 'invoiceClientId', 'conceptoIndex'], 'idx_return_history_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returnOrderItemHistory');
    }
};
