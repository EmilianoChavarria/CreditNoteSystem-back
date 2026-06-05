<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE returnorders MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'in process'");
        DB::statement("UPDATE returnorders SET `status` = 'released'   WHERE `status` = 'approved'");
        DB::statement("UPDATE returnorders SET `status` = 'cancelled'  WHERE `status` = 'rejected'");
        DB::statement("UPDATE returnorders SET `status` = 'in process' WHERE `status` = 'pending'");
        DB::statement("ALTER TABLE returnorders MODIFY COLUMN `status` ENUM('in process', 'cancelled', 'released') NOT NULL DEFAULT 'in process'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE returnorders MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending'");
        DB::statement("UPDATE returnorders SET `status` = 'pending'  WHERE `status` = 'in process'");
        DB::statement("UPDATE returnorders SET `status` = 'approved' WHERE `status` = 'released'");
        DB::statement("ALTER TABLE returnorders MODIFY COLUMN `status` ENUM('pending', 'approved', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
