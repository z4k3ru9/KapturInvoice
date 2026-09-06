<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A company's reusable named tax rates. Applied to invoice items via
     * the `invoice_item_taxes` pivot (see its migration) instead of the
     * legacy pattern of hard-coded tax_name1/rate1 + tax_name2/rate2
     * columns duplicated on every taxable table.
     */
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_tax_rate_id')->nullable();

            $table->string('name');
            $table->decimal('rate', 8, 3);
            $table->boolean('is_inclusive')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
