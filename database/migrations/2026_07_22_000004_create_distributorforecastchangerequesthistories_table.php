<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributorforecastchangerequesthistories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributorForecastChangeRequestId');
            $table->enum('action', ['submitted', 'approved', 'rejected', 'auto_approved']);
            $table->integer('actorUserId');
            $table->integer('forecast');
            $table->enum('step', ['sales_manager', 'general_manager', 'auto_approved']);
            $table->timestamp('createdAt');

            $table->foreign('distributorForecastChangeRequestId', 'dfcrh_change_request_fk')
                ->references('id')->on('distributorforecastchangerequests')->onDelete('cascade');
            $table->foreign('actorUserId')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributorforecastchangerequesthistories');
    }
};
