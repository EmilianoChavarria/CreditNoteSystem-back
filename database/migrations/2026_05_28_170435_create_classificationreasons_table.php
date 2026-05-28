<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classificationreasons', function (Blueprint $table) {
            $table->integer('classificationId');
            $table->integer('reasonId');

            $table->primary(['classificationId', 'reasonId']);

            $table->foreign('classificationId')
                ->references('id')->on('requestclassification')
                ->onDelete('cascade');

            $table->foreign('reasonId')
                ->references('id')->on('requestreasons')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classificationreasons');
    }
};
