<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A new, canonical aggregate for Phase 03
     * (docs/rebuild/specs/03-sales-and-job/Specs.md) — deliberately NOT the
     * existing `invoices` table's `type = 'quote'` rows (see
     * App\Livewire\TallStackQuotes's docblock — the Filament
     * `QuoteResource` this originally referenced no longer exists, per
     * CLAUDE.md's note on the Filament removal — and
     * docs/REFACTOR_PLAN.md §1.2's "MODIFY (major) ... becomes its own
     * QuotationResource over the new quotations table"). Per
     * docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md §7 ("Introduce new
     * canonical classes beside legacy classes until the migration wave
     * proves the replacement"), the legacy Invoice/type=quote rows stay
     * exactly as-is (still serving already-imported legacy quotes); this
     * table is where every *new* quotation is created from Phase 03 on.
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('number')->nullable();
            $table->string('status')->default('draft'); // App\Enums\QuotationStatus

            // FINALIZED-DECISIONS.md §3: one pricing mode per document,
            // never mixed. Full tax computation against this mode is
            // Phase 04 scope (App\Services\TaxCalculationService); this
            // phase only stores the selection.
            $table->string('pricing_mode')->default('exclusive'); // exclusive|inclusive

            $table->decimal('discount', 13, 2)->default(0);
            $table->boolean('discount_is_percentage')->default(false);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->date('quotation_date')->nullable();
            $table->date('valid_until')->nullable();

            // Customer PO / Customer Order Confirmation (FINALIZED-DECISIONS.md
            // §2, Specs.md §requirements): recorded at acceptance time, never
            // before — see App\Actions\Sales\AcceptQuotation.
            $table->string('customer_po_number')->nullable();
            $table->date('customer_po_date')->nullable();
            $table->boolean('customer_po_is_system_generated')->default(false);

            $table->text('terms')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
