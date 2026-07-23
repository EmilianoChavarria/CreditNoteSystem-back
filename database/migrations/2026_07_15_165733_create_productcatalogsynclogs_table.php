<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productcatalogsynclogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('recordsSynced')->default(0);
            $table->string('status', 20);
            $table->text('errorMessage')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productcatalogsynclogs');
    }
};
