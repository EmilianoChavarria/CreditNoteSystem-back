<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE batches MODIFY COLUMN batchType ENUM('sapScreen','creditsData','orderNumbers','newRequest','uploadSupport','users','forecast') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE batches MODIFY COLUMN batchType ENUM('sapScreen','creditsData','orderNumbers','newRequest','uploadSupport','users') NOT NULL");
    }
};
