<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 04 (docs/rebuild/specs/04-billing-and-receivables): extends the
     * existing `invoices` table rather than splitting a new one out (unlike
     * Quotation/SalesOrder in Phase 03) — real invoices are MODIFY (major),
     * not BUILD NEW, since already-imported financial history already
     * lives here.
     *
     * - `sales_order_id`: optional link to the Phase 03 Job aggregate this
     *   invoice bills against (a legacy/already-imported invoice has none).
     * - `pricing_mode`: one tax mode per document, per
     *   FINALIZED-DECISIONS.md §3 (reuses App\Enums\PricingMode).
     * - `approved_at`/`issued_at`/`voided_at`: lifecycle timestamps for the
     *   new Draft -> Approved -> Issued states and the Void correction path.
     * - `original_invoice_id`: self-referential link from an amendment or
     *   reissued invoice back to the one it corrects — the original is
     *   never edited or deleted, per FINALIZED-DECISIONS.md §2.
     * - `void_reason`/`correction_reason`: the required reason text for a
     *   void-and-reissue or an amendment, respectively.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('client_id')
                ->constrained('sales_orders')->nullOnDelete();
            $table->string('pricing_mode')->default('exclusive')->after('type');

            $table->timestamp('approved_at')->nullable()->after('sent_at');
            $table->timestamp('issued_at')->nullable()->after('approved_at');
            $table->timestamp('voided_at')->nullable()->after('issued_at');

            $table->foreignId('original_invoice_id')->nullable()->after('converted_from_quote_id')
                ->constrained('invoices')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('original_invoice_id');
            $table->text('correction_reason')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_id');
            $table->dropConstrainedForeignId('original_invoice_id');
            $table->dropColumn(['pricing_mode', 'approved_at', 'issued_at', 'voided_at', 'void_reason', 'correction_reason']);
        });
    }
};
