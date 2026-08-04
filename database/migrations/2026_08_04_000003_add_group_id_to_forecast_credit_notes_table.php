<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // NULL cuando la NC se generó directamente para un cliente individual;
        // seteado al grupo cuando la NC es de uno de sus clientes miembro que aportó ventas.
        // Se usa SQL directo (en vez de unsignedBigInteger() fluido) para garantizar BIGINT
        // UNSIGNED exacto, requerido para que la FK contra client_groups.id (bigint unsigned) matchee.
        DB::statement('ALTER TABLE forecast_credit_notes ADD COLUMN groupId BIGINT UNSIGNED NULL AFTER customerNumber');
        DB::statement('ALTER TABLE forecast_credit_notes ADD CONSTRAINT forecast_credit_notes_groupid_foreign FOREIGN KEY (groupId) REFERENCES client_groups(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX forecast_credit_notes_groupid_year_month_index ON forecast_credit_notes (groupId, year, month)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE forecast_credit_notes DROP FOREIGN KEY forecast_credit_notes_groupid_foreign');
        DB::statement('DROP INDEX forecast_credit_notes_groupid_year_month_index ON forecast_credit_notes');
        DB::statement('ALTER TABLE forecast_credit_notes DROP COLUMN groupId');
    }
};
