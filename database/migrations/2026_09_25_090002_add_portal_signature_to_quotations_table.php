<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public drawn-signature acceptance for a Quotation — the same
     * capability as the invoice portal e-sign flow, layered onto the
     * existing `App\Actions\Sales\AcceptQuotation` lifecycle rather than
     * bypassing it: `App\Livewire\Portal\SignQuotation::accept()` still
     * calls that action (so the Customer Order Confirmation / customer-PO
     * logic keeps deciding what it already decides), then additionally
     * records the drawn signature captured alongside it. `signed_at` is
     * deliberately its own column, not reused from `accepted_at` — it
     * marks specifically when the drawn signature was captured, which for
     * a quotation happens to be the same moment as acceptance today, but
     * keeping them separate matches the Delivery Order/Handover Report
     * shape and doesn't assume every future acceptance path is signed.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('portal_key')->nullable()->unique();
            $table->string('signed_by_name')->nullable();
            $table->longText('signature')->nullable();
            $table->timestamp('signed_at')->nullable();
        });

        DB::table('quotations')->whereNull('portal_key')->orderBy('id')->each(function ($row) {
            DB::table('quotations')->where('id', $row->id)->update([
                'portal_key' => (string) Str::orderedUuid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['portal_key', 'signed_by_name', 'signature', 'signed_at']);
        });
    }
};
