<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributorforecasts', function (Blueprint $table) {
            $table->id();
            $table->integer('distributorId');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->integer('forecast')->default(0);
            $table->integer('sales')->default(0);
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');

            $table->unique(['distributorId', 'year', 'month']);
            $table->foreign('distributorId')->references('id')->on('distributors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributorforecasts');
    }
};
