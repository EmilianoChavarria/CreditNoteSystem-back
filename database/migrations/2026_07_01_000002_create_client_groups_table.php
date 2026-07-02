<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });

        Schema::create('client_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('groupId');
            $table->unsignedInteger('clientId');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['groupId', 'clientId']);
            $table->foreign('groupId')->references('id')->on('client_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_group_members');
        Schema::dropIfExists('client_groups');
    }
};
