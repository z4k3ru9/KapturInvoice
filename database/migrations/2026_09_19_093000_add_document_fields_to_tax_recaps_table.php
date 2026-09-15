<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 06B (docs/rebuild/specs/06b-ux-browser-soa/Specs.md): Tax recap
     * joins the required launch document coverage list ("Bahasa default,
     * English override, translation keys, A4 layout, rendering test" for
     * every launch document type) — it needs its own internal launch-code
     * number (`TAX`, FINALIZED-DECISIONS.md §2) and a document-language
     * override, the same pattern already applied to
     * Quotation/VendorPurchaseOrder/VendorBill/VendorPaymentReceipt/
     * DeliveryOrder/HandoverReport in the neighboring migrations. `number`
     * is purely the internal numbered identifier — separate from the
     * existing manual-adjustment fields (`external_reference`,
     * `manual_entry_status`).
     */
    public function up(): void
    {
        Schema::table('tax_recaps', function (Blueprint $table) {
            $table->string('number')->nullable()->after('invoice_id');
            $table->string('document_language')->nullable()->after('attachment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('tax_recaps', function (Blueprint $table) {
            $table->dropColumn(['number', 'document_language']);
        });
    }
};
