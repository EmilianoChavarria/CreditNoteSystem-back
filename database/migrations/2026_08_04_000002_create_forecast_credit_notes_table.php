<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->integer('requestId');
            $table->enum('entityType', ['cliente', 'grupo']);
            $table->unsignedInteger('entityId');
            $table->string('customerNumber', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('returnPercentage', 5, 2);
            $table->decimal('salesAmount', 14, 2);
            $table->decimal('totalAmount', 14, 2);
            $table->text('invoiceFolios');
            $table->integer('generatedBy');
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('requestId')->references('id')->on('requests')->onDelete('cascade');
            $table->foreign('generatedBy')->references('id')->on('users')->onDelete('restrict');
            $table->unique(['entityType', 'entityId', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_credit_notes');
    }
};
