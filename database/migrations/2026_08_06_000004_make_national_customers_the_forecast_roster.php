<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_customers', function (Blueprint $table) {
            $table->softDeletes('deletedAt');
        });

        // El alta masiva por ids (Postman) registra al participante sin sus ajustes;
        // correos y % de retorno se capturan después desde la edición.
        Schema::table('national_customers', function (Blueprint $table) {
            $table->string('emails', 500)->nullable()->change();
            $table->decimal('returnPercentage', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('national_customers', function (Blueprint $table) {
            $table->dropSoftDeletes('deletedAt');
        });

        Schema::table('national_customers', function (Blueprint $table) {
            $table->string('emails', 500)->nullable(false)->change();
            $table->decimal('returnPercentage', 5, 2)->nullable(false)->change();
        });
    }
};
