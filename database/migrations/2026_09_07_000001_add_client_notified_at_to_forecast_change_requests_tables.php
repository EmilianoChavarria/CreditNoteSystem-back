<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marca el momento en que se avisó al cliente que su objetivo cambió. El
        // aviso lo manda un scheduler diario que agrupa todos los meses aprobados
        // de un mismo cliente en un solo correo, por eso no basta con el status.
        Schema::table('forecastchangerequests', function (Blueprint $table) {
            $table->timestamp('clientNotifiedAt')->nullable()->after('submittedByUserId');
        });

        Schema::table('distributorforecastchangerequests', function (Blueprint $table) {
            $table->timestamp('clientNotifiedAt')->nullable()->after('submittedByUserId');
        });

        // Las solicitudes ya aprobadas antes de este cambio recibieron su correo
        // en el momento de la aprobación: se marcan como notificadas para que el
        // scheduler no las vuelva a enviar.
        DB::table('forecastchangerequests')
            ->where('status', 'approved')
            ->update(['clientNotifiedAt' => now()]);

        DB::table('distributorforecastchangerequests')
            ->where('status', 'approved')
            ->update(['clientNotifiedAt' => now()]);
    }

    public function down(): void
    {
        Schema::table('forecastchangerequests', function (Blueprint $table) {
            $table->dropColumn('clientNotifiedAt');
        });

        Schema::table('distributorforecastchangerequests', function (Blueprint $table) {
            $table->dropColumn('clientNotifiedAt');
        });
    }
};
