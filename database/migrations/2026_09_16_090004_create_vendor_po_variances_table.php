<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "A vendor bill can exceed its approved Vendor PO only as a recorded
     * variance. Payment above the PO total is blocked until Admin or
     * Owner approves the variance with a reason; the original PO remains
     * immutable." — FINALIZED-DECISIONS.md §4. Append-only, mirrors
     * `job_variations` (Phase 03): written only by
     * App\Actions\Procurement\ApproveVendorPoVariance, which raises the
     * effective payment ceiling without ever editing
     * `vendor_purchase_orders.total`.
     */
    public function up(): void
    {
        Schema::create('vendor_po_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->decimal('amount', 15, 2); // additional ceiling granted, always positive
            $table->timestamp('approved_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_po_variances');
    }
};
