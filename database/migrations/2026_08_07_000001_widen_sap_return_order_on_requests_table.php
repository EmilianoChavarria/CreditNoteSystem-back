<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // sapReturnOrder guarda la URL del archivo de SAP screen (Storage::url),
        // que supera los 100 caracteres del ancho original.
        DB::statement('ALTER TABLE `requests` MODIFY `sapReturnOrder` VARCHAR(500) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `requests` MODIFY `sapReturnOrder` VARCHAR(100) NULL');
    }
};
