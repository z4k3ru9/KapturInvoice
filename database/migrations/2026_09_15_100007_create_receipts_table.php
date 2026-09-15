<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "One verified payment event creates exactly one numbered receipt" —
     * FINALIZED-DECISIONS.md §4. `payment_id` is unique so a second
     * App\Actions\Receivables\IssuePaymentReceipt call for the same
     * payment fails at the database, not just in application logic.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->timestamp('issued_at');

            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
