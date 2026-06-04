<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returnorderrequestitems', function (Blueprint $table) {
            $table->integer('rejectedReplenishmentBy')->nullable()->after('replenishmentReasonForRejection');
            $table->integer('rejectedWarehouseBy')->nullable()->after('warehouseReasonForRejection');

            $table->foreign('rejectedReplenishmentBy')->references('id')->on('users');
            $table->foreign('rejectedWarehouseBy')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('returnorderrequestitems', function (Blueprint $table) {
            $table->dropForeign(['rejectedReplenishmentBy']);
            $table->dropForeign(['rejectedWarehouseBy']);
            $table->dropColumn(['rejectedReplenishmentBy', 'rejectedWarehouseBy']);
        });
    }
};
