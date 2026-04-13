<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('assigned_request','batch_finished','assigned_request_bulk') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE notifications SET type = 'assigned_request' WHERE type = 'assigned_request_bulk'");
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('assigned_request','batch_finished') NOT NULL");
    }
};
