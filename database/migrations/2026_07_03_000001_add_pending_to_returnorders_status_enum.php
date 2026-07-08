<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE returnorders MODIFY status ENUM('pending','in process','cancelled','released') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE returnorders SET status = 'in process' WHERE status = 'pending'");
        DB::statement("ALTER TABLE returnorders MODIFY status ENUM('in process','cancelled','released') NOT NULL DEFAULT 'in process'");
    }
};
