<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A curated per-tenant gateway list, replacing InvoiceNinja's
     * `account_gateways` (+ `account_gateway_settings` fee config) against
     * its own ~69-driver catalog — see
     * docs/invoiceninja-v4-schema-reference.md §2.4. `config` holds
     * gateway API credentials and MUST stay encrypted at rest (see the
     * PaymentGateway model's casts) rather than the legacy plaintext
     * column.
     */
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_account_gateway_id')->nullable();

            $table->string('name');
            $table->string('driver'); // stripe|midtrans|xendit|paypal|manual...
            $table->boolean('is_enabled')->default(false);
            $table->text('config')->nullable(); // encrypted casts, see PaymentGateway model
            $table->json('accepted_credit_cards')->nullable();
            $table->boolean('show_address')->default(false);
            $table->boolean('require_cvv')->default(true);

            $table->decimal('fee_amount', 13, 2)->default(0);
            $table->decimal('fee_percent', 8, 3)->default(0);
            $table->string('fee_tax_name')->nullable();
            $table->decimal('fee_tax_rate', 8, 3)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
