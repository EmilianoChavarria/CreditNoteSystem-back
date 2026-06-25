<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE forecastchangerequesthistories
            MODIFY COLUMN `action` ENUM(
                'submitted',
                'approved',
                'rejected',
                'auto_approved'
            ) NOT NULL
        ");

        DB::statement("
            ALTER TABLE forecastchangerequesthistories
            MODIFY COLUMN `step` ENUM(
                'sales_manager',
                'general_manager',
                'auto_approved'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE forecastchangerequesthistories
            MODIFY COLUMN `action` ENUM(
                'submitted',
                'approved',
                'rejected'
            ) NOT NULL
        ");

        DB::statement("
            ALTER TABLE forecastchangerequesthistories
            MODIFY COLUMN `step` ENUM(
                'sales_manager',
                'general_manager'
            ) NOT NULL
        ");
    }
};
