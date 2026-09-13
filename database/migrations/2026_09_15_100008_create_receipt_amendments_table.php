<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "If an allocation changes after the receipt is issued, preserve the
     * original receipt snapshot and create a numbered Receipt Amendment /
     * Allocation Adjustment linked to the same payment event." —
     * FINALIZED-DECISIONS.md §4. Written only by
     * App\Actions\Receivables\AmendPaymentAllocation; the linked `receipts`
     * row is never edited.
     */
    public function up(): void
    {
        Schema::create('receipt_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->json('allocations_before');
            $table->json('allocations_after');
            $table->foreignId('amended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('amended_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_amendments');
    }
};
