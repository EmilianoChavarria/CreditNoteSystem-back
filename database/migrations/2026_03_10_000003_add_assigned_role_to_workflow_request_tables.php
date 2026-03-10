<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflowRequestSteps', function (Blueprint $table) {
            if (!Schema::hasColumn('workflowRequestSteps', 'assignedRoleId')) {
                $table->unsignedInteger('assignedRoleId')->nullable()->after('workflowStepId');
                $table->index('assignedRoleId', 'workflowRequestSteps_assignedRoleId_idx');
            }
        });

        Schema::table('workflowRequestCurrentStep', function (Blueprint $table) {
            if (!Schema::hasColumn('workflowRequestCurrentStep', 'assignedRoleId')) {
                $table->unsignedInteger('assignedRoleId')->nullable()->after('workflowStepId');
                $table->index('assignedRoleId', 'workflowRequestCurrentStep_assignedRoleId_idx');
            }
        });

        $roleIdsByStep = DB::table('workflowSteps')
            ->pluck('roleId', 'id');

        DB::table('workflowRequestSteps')
            ->whereNull('assignedRoleId')
            ->select(['id', 'workflowStepId'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($roleIdsByStep): void {
                foreach ($rows as $row) {
                    $roleId = $roleIdsByStep->get($row->workflowStepId);

                    if ($roleId !== null) {
                        DB::table('workflowRequestSteps')
                            ->where('id', $row->id)
                            ->update(['assignedRoleId' => $roleId]);
                    }
                }
            });

        DB::table('workflowRequestCurrentStep')
            ->whereNull('assignedRoleId')
            ->select(['id', 'workflowStepId'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($roleIdsByStep): void {
                foreach ($rows as $row) {
                    $roleId = $roleIdsByStep->get($row->workflowStepId);

                    if ($roleId !== null) {
                        DB::table('workflowRequestCurrentStep')
                            ->where('id', $row->id)
                            ->update(['assignedRoleId' => $roleId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflowRequestCurrentStep', function (Blueprint $table) {
            if (Schema::hasColumn('workflowRequestCurrentStep', 'assignedRoleId')) {
                $table->dropIndex('workflowRequestCurrentStep_assignedRoleId_idx');
                $table->dropColumn('assignedRoleId');
            }
        });

        Schema::table('workflowRequestSteps', function (Blueprint $table) {
            if (Schema::hasColumn('workflowRequestSteps', 'assignedRoleId')) {
                $table->dropIndex('workflowRequestSteps_assignedRoleId_idx');
                $table->dropColumn('assignedRoleId');
            }
        });
    }
};
