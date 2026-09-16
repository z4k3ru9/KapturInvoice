<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A defined unit of measure per line (App\Enums\UnitOfMeasure), not free
 * text — auto-filled from the selected product's own `unit`, editable per
 * line for an ad-hoc entry. See that enum's own docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
