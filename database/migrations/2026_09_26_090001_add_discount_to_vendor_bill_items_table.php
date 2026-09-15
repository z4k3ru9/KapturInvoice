<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-item discount for a Vendor Bill line — a vendor trade/volume
     * discount recorded on the bill line itself, mirroring `invoice_items`'
     * own `discount`/`discount_is_percentage` columns. Reduces this line's
     * own `net_amount` base before it is combined with `tax_amount` into
     * `line_total` — never the vendor's own charged `tax_amount`, which
     * stays untouched (no input-tax-credit engine is built here, per
     * FINALIZED-DECISIONS.md §3). GitHub issue #18.
     */
    public function up(): void
    {
        Schema::table('vendor_bill_items', function (Blueprint $table) {
            $table->decimal('discount', 13, 2)->default(0)->after('unit_cost');
            $table->boolean('discount_is_percentage')->default(false)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bill_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'discount_is_percentage']);
        });
    }
};
