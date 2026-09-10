<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre con el que el cliente aparece en SAP. En el módulo de forecast se
 * muestra y se busca por este nombre en lugar de la razón social fiscal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_customers', function (Blueprint $table) {
            $table->string('sapName')->nullable()->after('customerNumber');
        });
    }

    public function down(): void
    {
        Schema::table('national_customers', function (Blueprint $table) {
            $table->dropColumn('sapName');
        });
    }
};
