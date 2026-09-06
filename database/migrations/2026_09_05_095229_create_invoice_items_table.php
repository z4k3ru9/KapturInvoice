<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Line items snapshot their own title/cost at invoicing time (they
     * don't just live-join `products`), matching legacy invoice_items
     * behaviour so historical invoices don't change if a product's price
     * changes later.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('legacy_invoice_item_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('discount', 13, 2)->default(0);
            $table->boolean('discount_is_percentage')->default(false);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
