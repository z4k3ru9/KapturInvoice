<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The immutable "tax snapshot" from docs/rebuild/CONTEXT.md: values
     * captured the moment App\Actions\Billing\IssueInvoice issues a
     * document, so a later product/tax-rate/company-setting change can
     * never retroactively alter what was actually billed. One row per
     * issued invoice (each amendment is its own invoice row with its own
     * snapshot — see `invoices.original_invoice_id`).
     */
    public function up(): void
    {
        Schema::create('invoice_tax_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('pricing_mode');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('taxable_base_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);

            // Pre-round is the two-decimal computed total; rounding_adjustment
            // is the upward ceiling-to-whole-Rupiah delta; total is the final
            // payable value — all three kept per
            // FINALIZED-DECISIONS.md §3's "snapshot the pre-round amount,
            // final rounding adjustment, and rounded result."
            $table->decimal('pre_round_total', 15, 2)->default(0);
            $table->decimal('rounding_adjustment', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->json('items_snapshot');
            $table->timestamp('captured_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_tax_snapshots');
    }
};
