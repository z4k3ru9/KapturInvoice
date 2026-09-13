<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 02 (docs/rebuild/specs/02-parties-and-catalog/Specs.md):
     * broadens `Product` into a general catalog item — a line a
     * quotation/invoice can sell that isn't necessarily a physical good.
     * Per docs/REFACTOR_PLAN.md §1.1 this is an additive extension of the
     * existing table/model, not a new `catalog_items` table — `Product`
     * already cleanly separates "reference catalog" (`PriceListItem`) from
     * "sellable item", so the only real gap was the type/tax/stock fields
     * below.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('product')->after('name');
            $table->string('unit')->nullable()->after('type');
            $table->string('tax_category')->default('standard_taxable')->after('default_tax_rate_id');

            // A label only ("this is normally a stocked item"), never a
            // real inventory/availability signal — full inventory
            // (quantities on hand, reservations, valuation) is explicitly
            // deferred launch scope (Specs.md §3). Nothing may compute
            // "in stock" from this flag.
            $table->boolean('stock_flag')->default(false)->after('tax_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['type', 'unit', 'tax_category', 'stock_flag']);
        });
    }
};
