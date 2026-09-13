<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Linked amendment or reversal records for later corrections" —
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Mirrors
     * `payment_reversals` (Phase 04): the VendorPayment itself is never
     * deleted, only moved to VendorPaymentStatus::Reversed, with the
     * reason recorded here.
     */
    public function up(): void
    {
        Schema::create('vendor_payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('reversed_by_user_id')->constrained('users');
            $table->timestamp('reversed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_reversals');
    }
};
