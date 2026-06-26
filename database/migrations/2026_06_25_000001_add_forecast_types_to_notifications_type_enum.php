<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE notifications
            MODIFY COLUMN `type` ENUM(
                'assigned_request',
                'assigned_request_bulk',
                'batch_needs_attachments',
                'batch_finished',
                'forecast_pending_approval',
                'forecast_step_approved',
                'forecast_approved',
                'forecast_rejected'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE notifications
            MODIFY COLUMN `type` ENUM(
                'assigned_request',
                'assigned_request_bulk',
                'batch_needs_attachments',
                'batch_finished'
            ) NOT NULL
        ");
    }
};
