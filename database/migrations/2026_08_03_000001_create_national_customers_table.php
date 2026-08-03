<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('national_customers', function (Blueprint $table) {
            $table->id();
            $table->string('customerNumber', 50);
            $table->string('emails', 500);
            $table->decimal('returnPercentage', 5, 2);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique('customerNumber');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_customers');
    }
};
