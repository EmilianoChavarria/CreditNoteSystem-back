<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returnOrders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('clientId');
            $table->unsignedBigInteger('userId')->nullable();
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returnOrders');
    }
};
