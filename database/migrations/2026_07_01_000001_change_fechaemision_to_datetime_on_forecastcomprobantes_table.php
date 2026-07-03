<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE forecastcomprobantes MODIFY fechaEmision DATETIME NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE forecastcomprobantes MODIFY fechaEmision DATE NOT NULL');
    }
};
