<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workflowsteps', 'markAsApproved')) {
            Schema::table('workflowsteps', function (Blueprint $table) {
                $table->dropColumn('markAsApproved');
            });
        }

        Schema::table('workflowsteptransitions', function (Blueprint $table) {
            $table->boolean('markAsApproved')->default(false)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('workflowsteptransitions', function (Blueprint $table) {
            $table->dropColumn('markAsApproved');
        });

        Schema::table('workflowsteps', function (Blueprint $table) {
            $table->boolean('markAsApproved')->default(false)->after('isFinalStep');
        });
    }
};
