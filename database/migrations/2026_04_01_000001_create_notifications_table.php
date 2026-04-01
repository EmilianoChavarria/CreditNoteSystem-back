<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('userId');
            $table->enum('type', ['assigned_request', 'batch_finished']);
            $table->unsignedInteger('relatedId')->nullable();
            $table->string('title', 255);
            $table->longText('message');
            $table->boolean('isRead')->default(false);
            $table->timestamp('readAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('userId', 'fk_notifications_userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('userId', 'notifications_userId_idx');
            $table->index('type', 'notifications_type_idx');
            $table->index('isRead', 'notifications_isRead_idx');
            $table->index('createdAt', 'notifications_createdAt_idx');
            $table->index('relatedId', 'notifications_relatedId_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};