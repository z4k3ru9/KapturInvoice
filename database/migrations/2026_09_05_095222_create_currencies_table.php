<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Global reference data (not tenant-scoped), same role as the legacy
     * `currencies` table. Seed via database/seeders/CurrencySeeder.php.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('precision')->default(2);
            $table->string('thousand_separator', 5)->default(',');
            $table->string('decimal_separator', 5)->default('.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
