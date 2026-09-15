<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Payment corrections, rejected payments, and reversals preserve the
     * original payment and receipt history" — FINALIZED-DECISIONS.md §4.
     * Written only by App\Actions\Receivables\ReverseCustomerPayment; the
     * payment row itself moves to PaymentStatus::Reversed but is never
     * deleted, and any receipt already issued for it is untouched.
     */
    public function up(): void
    {
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reversals');
    }
};
