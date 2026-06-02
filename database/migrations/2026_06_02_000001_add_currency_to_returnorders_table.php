<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returnorders', function (Blueprint $table) {
            $table->enum('currency', ['MXN', 'USD'])->after('chargeTypeId');
        });
    }

    public function down(): void
    {
        Schema::table('returnorders', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
