<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emailconfig', function (Blueprint $table) {
            $table->enum('emailMode', ['normal', 'override', 'disabled'])->default('normal')->after('emailSupport');
            $table->string('overrideEmail', 255)->nullable()->after('emailMode');
        });
    }

    public function down(): void
    {
        Schema::table('emailconfig', function (Blueprint $table) {
            $table->dropColumn(['emailMode', 'overrideEmail']);
        });
    }
};
