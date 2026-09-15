<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-item discount for a Vendor Purchase Order line — a vendor
     * trade/volume discount recorded on the PO line itself, mirroring
     * `invoice_items`' own `discount`/`discount_is_percentage` columns
     * (see that table's migration for the same column shape/order).
     * GitHub issue #18.
     */
    public function up(): void
    {
        Schema::table('vendor_purchase_order_items', function (Blueprint $table) {
            $table->decimal('discount', 13, 2)->default(0)->after('unit_cost');
            $table->boolean('discount_is_percentage')->default(false)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'discount_is_percentage']);
        });
    }
};
