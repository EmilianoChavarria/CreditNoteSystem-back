<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecastchangerequesthistories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forecastChangeRequestId');
            $table->enum('action', ['submitted', 'approved', 'rejected']);
            $table->unsignedBigInteger('actorUserId');
            $table->decimal('amount', 15, 2);
            $table->enum('step', ['sales_manager', 'general_manager']);
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('forecastChangeRequestId', 'fk_fcr_history_request')
                ->references('id')
                ->on('forecastchangerequests')
                ->cascadeOnDelete();

            $table->index('forecastChangeRequestId');
            $table->index('actorUserId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecastchangerequesthistories');
    }
};
