<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Normalized replacement for the legacy expenses.tax_name1/rate1 +
     * tax_name2/rate2 columns — same shape as invoice_item_taxes.
     */
    public function up(): void
    {
        Schema::create('expense_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('expense_taxes');
    }
};
