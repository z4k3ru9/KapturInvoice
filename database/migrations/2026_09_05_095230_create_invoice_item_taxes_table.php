<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Normalized replacement for the legacy tax_name1/tax_rate1 +
     * tax_name2/tax_rate2 columns (repeated on accounts/clients/invoices/
     * invoice_items/products/expenses in InvoiceNinja v4). Each invoice
     * item can carry any number of applied taxes; name/rate are snapshotted
     * at invoicing time so a later edit to the tax_rates library doesn't
     * rewrite history.
     */
    public function up(): void
    {
        Schema::create('invoice_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->decimal('rate', 8, 3);
            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_taxes');
    }
};
