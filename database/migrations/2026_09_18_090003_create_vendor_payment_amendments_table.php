<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Linked amendment or reversal records for later corrections... [do
     * not mutate] the original event." — docs/rebuild/specs/
     * FINALIZED-DECISIONS.md §7. Mirrors `receipt_amendments` (Phase 04):
     * once a Vendor Payment Receipt exists, a correction (e.g. an amount
     * transcription error) is recorded here as a before/after snapshot
     * rather than editing the VendorPayment or its receipt in place.
     */
    public function up(): void
    {
        Schema::create('vendor_payment_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_receipt_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->json('changes_before');
            $table->json('changes_after');
            $table->foreignId('amended_by_user_id')->constrained('users');
            $table->timestamp('amended_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_amendments');
    }
};
