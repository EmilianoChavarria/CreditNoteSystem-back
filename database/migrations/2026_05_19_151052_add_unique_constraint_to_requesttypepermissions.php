<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if constraint already exists before adding
        $exists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'requesttypepermissions'
              AND CONSTRAINT_NAME = 'uq_rtp_role_requesttype_action'
        ");

        if ((int) $exists[0]->cnt === 0) {
            Schema::table('requesttypepermissions', function (Blueprint $table) {
                $table->unique(['role_id', 'request_type_id', 'action_id'], 'uq_rtp_role_requesttype_action');
            });
        }
    }

    public function down(): void
    {
        Schema::table('requesttypepermissions', function (Blueprint $table) {
            $table->dropUnique('uq_rtp_role_requesttype_action');
        });
    }
};
