<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returnorderitems', function (Blueprint $table) {
            $table->softDeletes('deletedAt');
            $table->unsignedBigInteger('deletedBy')->nullable()->after('deletedAt');
        });
    }

    public function down(): void
    {
        Schema::table('returnorderitems', function (Blueprint $table) {
            $table->dropColumn('deletedBy');
            $table->dropSoftDeletes('deletedAt');
        });
    }
};
