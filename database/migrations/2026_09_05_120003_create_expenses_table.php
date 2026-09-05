<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * See docs/invoiceninja-v4-schema-reference.md §2.5. `should_be_invoiced`
     * + `client_id` support rebilling a vendor expense onto a client's
     * invoice; `invoice_id` records where it landed once rebilled.
     * subtotal/tax_total/total are recomputed by ExpenseTotalsCalculator
     * from `expense_taxes`, mirroring the invoice/invoice_item_taxes
     * pattern rather than the legacy inline tax_name1/rate1 columns.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('legacy_expense_id')->nullable();

            $table->date('expense_date')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 13, 4)->default(1);

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->boolean('should_be_invoiced')->default(false);
            $table->string('transaction_reference')->nullable();
            $table->text('private_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
