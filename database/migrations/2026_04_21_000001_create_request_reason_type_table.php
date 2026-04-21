<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requestreasontype', function (Blueprint $table) {
            $table->integer('reasonId');
            $table->integer('requestTypeId');

            $table->primary(['reasonId', 'requestTypeId']);

            $table->foreign('reasonId')
                ->references('id')
                ->on('requestreasons');

            $table->foreign('requestTypeId')
                ->references('id')
                ->on('requesttype');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requestreasontype');
    }
};
