<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_ENUM = "ENUM('assigned_request','assigned_request_bulk','batch_needs_attachments','batch_finished','forecast_pending_approval','forecast_step_approved','forecast_approved','forecast_rejected')";

    private const NEW_ENUM = "ENUM('assigned_request','assigned_request_bulk','batch_needs_attachments','batch_finished','forecast_pending_approval','forecast_step_approved','forecast_approved','forecast_rejected','distributor_forecast_pending_approval','distributor_forecast_step_approved','distributor_forecast_approved','distributor_forecast_rejected')";

    public function up(): void
    {
        DB::statement('ALTER TABLE notifications MODIFY COLUMN type ' . self::NEW_ENUM . ' NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications MODIFY COLUMN type ' . self::OLD_ENUM . ' NOT NULL');
    }
};
