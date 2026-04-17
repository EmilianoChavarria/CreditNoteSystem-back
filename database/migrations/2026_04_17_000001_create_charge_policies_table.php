<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargePolicies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('day');
            $table->decimal('percentage', 5, 2);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->softDeletes('deletedAt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargePolicies');
    }
};
