<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_customers', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('returnPercentage');
        });
    }

    public function down(): void
    {
        Schema::table('national_customers', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
