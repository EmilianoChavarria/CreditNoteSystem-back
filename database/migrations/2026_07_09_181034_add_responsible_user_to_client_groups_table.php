<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_groups', function (Blueprint $table) {
            $table->integer('responsibleUserId')->nullable()->after('description');
            $table->foreign('responsibleUserId')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_groups', function (Blueprint $table) {
            $table->dropForeign(['responsibleUserId']);
            $table->dropColumn('responsibleUserId');
        });
    }
};
