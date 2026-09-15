<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 04: a payment is one real-world event that may be allocated
     * across several invoices (see `payment_allocations`), so `invoice_id`
     * stays only for already-imported legacy 1:1 payments — new payments
     * leave it null. Adds the fields the new verify/allocate/receipt flow
     * needs, per FINALIZED-DECISIONS.md §4.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('invoice_id')
                ->constrained('sales_orders')->nullOnDelete();
            $table->string('proof_path')->nullable()->after('notes');
            $table->string('reference')->nullable()->after('proof_path');

            $table->timestamp('cheque_cleared_at')->nullable()->after('reference');
            $table->timestamp('verified_at')->nullable()->after('cheque_cleared_at');
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_id');
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['proof_path', 'reference', 'cheque_cleared_at', 'verified_at']);
        });
    }
};
