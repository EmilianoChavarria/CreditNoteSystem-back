<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributorforecastchangerequests', function (Blueprint $table) {
            $table->id();
            $table->integer('distributorId');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->integer('previousForecast')->default(0);
            $table->integer('proposedForecast');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('currentStep', ['sales_manager', 'general_manager', 'auto_approved']);
            $table->integer('approverUserId');
            $table->integer('submittedByUserId');
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');

            $table->index(['distributorId', 'year', 'month', 'status'], 'dfcr_distributor_year_month_status_idx');
            $table->index('approverUserId');
            $table->index('submittedByUserId');

            $table->foreign('distributorId')->references('id')->on('distributors')->onDelete('cascade');
            $table->foreign('approverUserId')->references('id')->on('users');
            $table->foreign('submittedByUserId')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributorforecastchangerequests');
    }
};
