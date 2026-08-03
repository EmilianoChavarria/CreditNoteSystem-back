<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_groups', function (Blueprint $table) {
            $table->integer('salesManagerId')->nullable()->after('responsibleUserId');

            $table->foreign('salesManagerId')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('client_groups', function (Blueprint $table) {
            $table->dropForeign(['salesManagerId']);
            $table->dropColumn('salesManagerId');
        });
    }
};
