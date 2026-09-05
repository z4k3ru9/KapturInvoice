<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_client_id')->nullable();

            $table->string('name');
            $table->string('currency_code', 3)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2)->nullable();

            $table->string('tax_number')->nullable();
            $table->string('id_number')->nullable();

            // Denormalized running ledger (mirrors legacy clients.balance /
            // paid_to_date) — recompute from invoices/payments/credits via
            // App\Services\RecalculatesClientBalance rather than trusting
            // drift; see docs/invoiceninja-v4-schema-reference.md §5.
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('paid_to_date', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'legacy_client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
