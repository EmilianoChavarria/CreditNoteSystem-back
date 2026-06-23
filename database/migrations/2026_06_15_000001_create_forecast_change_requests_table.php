<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastchangerequests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idClient');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('previousAmount', 15, 2)->default(0);
            $table->decimal('proposedAmount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('currentStep', ['sales_manager', 'general_manager']);
            $table->unsignedBigInteger('approverUserId');
            $table->unsignedBigInteger('submittedByUserId');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->index(['idClient', 'year', 'month', 'status']);
            $table->index('approverUserId');
            $table->index('submittedByUserId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastchangerequests');
    }
};
