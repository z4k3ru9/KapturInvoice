<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an invoice line override its product's tax_category (Phase 02),
     * or set one at all for a custom line with no product — falls back to
     * the linked product's category, then to StandardTaxable, in
     * App\Services\Tax\TaxCalculationService. See
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §3.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('tax_category')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }
};
