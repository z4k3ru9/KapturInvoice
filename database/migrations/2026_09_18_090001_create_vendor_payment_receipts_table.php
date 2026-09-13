<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "One verified event produces one Vendor Payment Receipt." —
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Mirrors `receipts`
     * (Phase 04): one row per verified VendorPayment, DB-enforced via the
     * unique `vendor_payment_id`, carrying the launch-code 'VPR' number
     * (docs/rebuild/specs/FINALIZED-DECISIONS.md §2) that Phase 05
     * originally — incorrectly — assigned to the raw VendorPayment record
     * at record time instead of to this receipt at issuance.
     */
    public function up(): void
    {
        Schema::create('vendor_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_receipts');
    }
};
