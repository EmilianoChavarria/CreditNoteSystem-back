<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastsales', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idClient');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['idClient', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastsales');
    }
};
