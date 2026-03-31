<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflowRequestSteps', function (Blueprint $table) {
            if (!Schema::hasColumn('workflowRequestSteps', 'assignedUserId')) {
                $table->unsignedInteger('assignedUserId')->nullable()->after('assignedRoleId');
                $table->index('assignedUserId', 'workflowRequestSteps_assignedUserId_idx');
            }
        });

        Schema::table('workflowRequestCurrentStep', function (Blueprint $table) {
            if (!Schema::hasColumn('workflowRequestCurrentStep', 'assignedUserId')) {
                $table->unsignedInteger('assignedUserId')->nullable()->after('assignedRoleId');
                $table->index('assignedUserId', 'workflowRequestCurrentStep_assignedUserId_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflowRequestSteps', function (Blueprint $table) {
            if (Schema::hasColumn('workflowRequestSteps', 'assignedUserId')) {
                $table->dropIndex('workflowRequestSteps_assignedUserId_idx');
                $table->dropColumn('assignedUserId');
            }
        });

        Schema::table('workflowRequestCurrentStep', function (Blueprint $table) {
            if (Schema::hasColumn('workflowRequestCurrentStep', 'assignedUserId')) {
                $table->dropIndex('workflowRequestCurrentStep_assignedUserId_idx');
                $table->dropColumn('assignedUserId');
            }
        });
    }
};
