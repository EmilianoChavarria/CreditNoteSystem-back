<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::getDatabaseName();

        $fkExists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $schema)
            ->where('TABLE_NAME', 'workflowrequesthistory')
            ->where('CONSTRAINT_NAME', 'fkRequestWorkflowHistoryStep')
            ->exists();

        if ($fkExists) {
            DB::statement('ALTER TABLE `workflowrequesthistory` DROP FOREIGN KEY `fkRequestWorkflowHistoryStep`');
        }

        DB::statement('ALTER TABLE `workflowrequesthistory` ADD CONSTRAINT `fkRequestWorkflowHistoryStep` FOREIGN KEY (`requestWorkflowStepId`) REFERENCES `workflowrequeststeps` (`id`)');
    }

    public function down(): void
    {
        $schema = DB::getDatabaseName();

        $fkExists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $schema)
            ->where('TABLE_NAME', 'workflowrequesthistory')
            ->where('CONSTRAINT_NAME', 'fkRequestWorkflowHistoryStep')
            ->exists();

        if ($fkExists) {
            DB::statement('ALTER TABLE `workflowrequesthistory` DROP FOREIGN KEY `fkRequestWorkflowHistoryStep`');
        }

        DB::statement('ALTER TABLE `workflowrequesthistory` ADD CONSTRAINT `fkRequestWorkflowHistoryStep` FOREIGN KEY (`requestWorkflowStepId`) REFERENCES `workflowrequesthistory` (`id`)');
    }
};
