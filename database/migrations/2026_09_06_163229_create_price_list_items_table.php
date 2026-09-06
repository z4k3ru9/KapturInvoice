<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reference catalog imported from a vendor's own pricelist spreadsheet
 * (e.g. Hikvision/HiLook dealer pricelists — see
 * App\Services\PriceListImporter and docs/price-list-import.md), kept
 * separate from `products` (the company's own sellable catalog, used
 * directly on invoices). `products.price_list_item_id` links the two so a
 * product can be created/refreshed from a price list row without the two
 * ever being the same table — the vendor's raw catalog is much larger and
 * churns independently of what this company actually stocks/sells.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('brand');
            $table->string('category')->nullable();
            $table->string('sku');
            $table->text('description')->nullable();

            // Every price tier found in the source file, keyed by its own
            // column header verbatim (tier names/counts vary by vendor and
            // even by file version — see the importer for real examples).
            $table->json('prices')->nullable();
            // The one tier picked as "the" price for quick reference/sync
            // onto a Product's unit_cost — see PriceListImporter::pickReferencePrice().
            $table->decimal('reference_price', 15, 4)->nullable();

            $table->string('source_file')->nullable();
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'brand', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
