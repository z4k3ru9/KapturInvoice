<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 05 (docs/rebuild/specs/05-procurement-and-delivery): a NEW
     * aggregate beside the legacy `Expense` model, per the Expense-vs-
     * Vendor-Bill decision recorded in
     * docs/rebuild/outputs/19-phase-05-checkpoint-report.md — `Expense`
     * doesn't map 1:1 to "a vendor payable that may be paid in parts,
     * tied to a job's cost," so it stays frozen as a parallel non-job
     * cost bucket rather than being renamed/absorbed (mirrors the
     * Quotation/SalesOrder precedent from Phase 03).
     *
     * The PO's own `total` is immutable once recorded — a bill exceeding
     * it is handled by `vendor_po_variances`, never by editing this row
     * (FINALIZED-DECISIONS.md §4: "the original PO remains immutable").
     */
    public function up(): void
    {
        Schema::create('vendor_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->string('status')->default('draft'); // draft|approved|cancelled

            $table->date('po_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('total', 15, 2)->default(0);

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_purchase_orders');
    }
};
