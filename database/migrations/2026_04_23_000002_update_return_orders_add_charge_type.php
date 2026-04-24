<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('returnOrders', function (Blueprint $table) {
            $table->unsignedInteger('chargeTypeId')->nullable()->after('notes');
            $table->decimal('customRate', 5, 2)->nullable()->after('chargeTypeId');

            $table->foreign('chargeTypeId')
                ->references('id')
                ->on('chargeTypes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('returnOrders', function (Blueprint $table) {
            $table->dropForeign(['chargeTypeId']);
            $table->dropColumn(['chargeTypeId', 'customRate']);
        });

        Schema::table('returnOrders', function (Blueprint $table) {
            $table->boolean('charge')->default(true)->after('notes');
            $table->unsignedBigInteger('chargePolicyId')->nullable()->after('charge');
        });
    }
};
