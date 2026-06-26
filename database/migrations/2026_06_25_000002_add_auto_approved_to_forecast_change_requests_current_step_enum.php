<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE forecastchangerequests
            MODIFY COLUMN `currentStep` ENUM(
                'sales_manager',
                'general_manager',
                'auto_approved'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE forecastchangerequests
            MODIFY COLUMN `currentStep` ENUM(
                'sales_manager',
                'general_manager'
            ) NOT NULL
        ");
    }
};
