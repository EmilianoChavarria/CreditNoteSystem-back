<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_groups', function (Blueprint $table) {
            $table->string('clientNumber', 50)->nullable()->after('name');

            $table->unique('clientNumber');
        });
    }

    public function down(): void
    {
        Schema::table('client_groups', function (Blueprint $table) {
            $table->dropUnique(['clientNumber']);
            $table->dropColumn('clientNumber');
        });
    }
};
