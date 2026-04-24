<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargeTypes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('label');
            $table->decimal('percentage', 5, 2);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });

        DB::table('chargeTypes')->insert([
            ['name' => 'annual',   'label' => 'Anual',      'percentage' => 25.00, 'createdAt' => now(), 'updatedAt' => now()],
            ['name' => 'sporadic', 'label' => 'Esporádico', 'percentage' => 12.00, 'createdAt' => now(), 'updatedAt' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chargeTypes');
    }
};
