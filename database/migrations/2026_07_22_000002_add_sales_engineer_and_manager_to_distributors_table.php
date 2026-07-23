<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->integer('salesEngineerId')->nullable()->after('countrycode');
            $table->integer('salesManagerId')->nullable()->after('salesEngineerId');

            $table->foreign('salesEngineerId')->references('id')->on('users')->onDelete('set null');
            $table->foreign('salesManagerId')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropForeign(['salesEngineerId']);
            $table->dropForeign(['salesManagerId']);
            $table->dropColumn(['salesEngineerId', 'salesManagerId']);
        });
    }
};
