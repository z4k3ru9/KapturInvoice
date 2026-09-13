<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings VendorPayment in line with FINALIZED-DECISIONS.md §7 (added
     * after Phase 05 shipped a simple direct-record model): vendor
     * payments now go through the same Pending -> Verified -> (Reversed)
     * lifecycle as a customer Payment, including cheque-clearance
     * handling, before RecalculateVendorBillPayments counts them.
     */
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('amount');
            $table->timestamp('cheque_cleared_at')->nullable()->after('proof_path');
            $table->timestamp('verified_at')->nullable()->after('cheque_cleared_at');
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['status', 'cheque_cleared_at', 'verified_at']);
        });
    }
};
