<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastsynclogs', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned();
            $table->integer('recordsSynced')->default(0);
            $table->enum('status', ['success', 'failed']);
            $table->text('errorMessage')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastsynclogs');
    }
};
