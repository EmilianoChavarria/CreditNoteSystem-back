<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loginAttemptSettings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('maxUserAttempts')->default(5);
            $table->unsignedInteger('maxIpAttempts')->default(10);
            $table->unsignedInteger('sessionTimeoutMinutes')->default(120);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loginAttemptSettings');
    }
};