<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unlike Invoice (whose Sent -> Viewed status flip is driven by
     * Invitation::viewed_at, App\Livewire\Portal\ViewInvoice), Quotation's
     * portal page (App\Livewire\Portal\SignQuotation) is reached directly
     * by the quotation's own `portal_key`, with no wrapping Invitation
     * record to carry a view timestamp. Deliberately a plain informational
     * timestamp, NOT a new QuotationStatus::Viewed case: every place that
     * currently checks `status === Sent` (AcceptQuotation,
     * ExpireQuotations, TransitionQuotationStatus) would need auditing to
     * also accept a Viewed state, which is real ripple for what's meant to
     * be an informational "has the client opened this" signal, not a
     * lifecycle change. Not `#[Fillable]` — same precedent as Invoice's
     * `viewed_at`, written only by SignQuotation::mount().
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });
    }
};
