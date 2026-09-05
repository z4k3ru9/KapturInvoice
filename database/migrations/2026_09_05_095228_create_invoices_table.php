<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unifies invoices, quotes, and recurring invoice templates in one
     * table (`type` + `recurring_template_id`), mirroring the legacy
     * schema's `invoice_type_id`/`is_recurring`/`recurring_invoice_id`
     * pattern — see docs/invoiceninja-v4-schema-reference.md §2.3 for the
     * trade-off vs. splitting these into separate models.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_invoice_id')->nullable();

            $table->string('type')->default('invoice'); // invoice | quote
            $table->string('status')->default('draft'); // draft|sent|viewed|partial|paid|overdue|cancelled

            $table->string('number')->nullable();
            $table->string('po_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();

            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 13, 4)->default(1);

            $table->decimal('discount', 13, 2)->default(0);
            $table->boolean('discount_is_percentage')->default(false);

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);

            $table->decimal('partial_amount', 15, 2)->nullable();
            $table->date('partial_due_date')->nullable();

            $table->text('terms')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('footer')->nullable();

            // Recurring-invoice engine.
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency')->nullable(); // weekly|monthly|quarterly|yearly...
            $table->date('recurring_start_date')->nullable();
            $table->date('recurring_end_date')->nullable();
            $table->timestamp('recurring_last_sent_at')->nullable();
            $table->foreignId('recurring_template_id')->nullable()
                ->constrained('invoices')->nullOnDelete();
            $table->boolean('auto_bill')->default(false);

            // Quote -> invoice conversion link.
            $table->foreignId('converted_from_quote_id')->nullable()
                ->constrained('invoices')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
