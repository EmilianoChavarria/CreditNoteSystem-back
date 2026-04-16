<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returnOrderRequests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('returnOrderId')->unique();
            $table->unsignedBigInteger('requestId')->unique();
            $table->decimal('returnChargePercent', 5, 2)->default(0.00);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('returnOrderId')
                ->references('id')
                ->on('returnOrders')
                ->onDelete('cascade');

            $table->foreign('requestId')
                ->references('id')
                ->on('requests')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returnOrderRequests');
    }
};
