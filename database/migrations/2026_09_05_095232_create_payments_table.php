<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only masked/last4 card data ever lands here, matching legacy
     * discipline — full PANs belong to the gateway (Stripe/Midtrans/
     * Xendit/etc.), never this table. `gateway_reference` holds that
     * provider's own transaction/customer id.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('legacy_payment_id')->nullable();

            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 13, 4)->default(1);

            $table->string('method')->nullable(); // card|bank_transfer|cash|...
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('status')->default('completed'); // pending|completed|failed|refunded

            $table->string('last4', 4)->nullable();
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
