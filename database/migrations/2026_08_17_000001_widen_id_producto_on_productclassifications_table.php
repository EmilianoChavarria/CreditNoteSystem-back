<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // idProducto no es una clave corta: el catálogo trae identificadores
        // descriptivos ("-PUMP HSNG-SAE3-1\"SPCR RING") que pasan de 50 chars y
        // hacían fallar la carga masiva de clasificación.
        // 191 y no 255: la columna se usa como llave de búsqueda/único y con
        // utf8mb4 ese es el ancho máximo indexable en InnoDB con prefijo de 767 bytes.
        DB::statement('ALTER TABLE `productclassifications` MODIFY `idProducto` VARCHAR(191) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `productclassifications` MODIFY `idProducto` VARCHAR(50) NOT NULL');
    }
};
