<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Codifies the clientId column as varchar(50) — client IDs from the external
     * clients table can be alphanumeric (e.g. "121314-64580", "BAL620129Q59"),
     * not just plain integers.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE client_group_members MODIFY clientId VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE client_group_members MODIFY clientId INT UNSIGNED NOT NULL');
    }
};
