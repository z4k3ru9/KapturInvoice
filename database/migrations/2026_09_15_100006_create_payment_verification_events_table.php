<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One append-only row per verification attempt on a payment — written
     * only by App\Actions\Receivables\VerifyCustomerPayment. Bank
     * transfer/manual payments are typically verified once; a cheque may
     * accumulate a rejected attempt before a later cleared one, so this is
     * a log, not a single mutable field on `payments`.
     */
    public function up(): void
    {
        Schema::create('payment_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status'); // verified|rejected
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verification_events');
    }
};
