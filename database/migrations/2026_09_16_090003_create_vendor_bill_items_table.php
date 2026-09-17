<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Use gross vendor cost for job margin while preserving net/tax/gross
     * components." `line_total` is the gross cost (net_amount + tax_amount)
     * — the value job-cost allocation and margin reporting use; the split
     * is kept only for visibility (FINALIZED-DECISIONS.md §3: "For
     * Company A, vendor tax is permitted and treated as nonrecoverable gross
     * cost" — no input-tax-credit engine is built here).
     */
    public function up(): void
    {
        Schema::create('vendor_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_purchase_order_item_id')->nullable()
                ->constrained('vendor_purchase_order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_items');
    }
};
