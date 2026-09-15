<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair plan Phase 10b / decision gate G3: Products share one category
     * taxonomy with Price List Items (`price_list_items.category`, a plain
     * nullable string, not a lookup table — see that column's own
     * migration). A Product linked via `price_list_item_id` inherits its
     * source item's category as a one-time default
     * (App\Services\ProductSync); a hand-created Product picks from the
     * same shared distinct-value list via
     * resources/views/components/tallstack/category-select.blade.php
     * (Phase 10a).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('price_list_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
