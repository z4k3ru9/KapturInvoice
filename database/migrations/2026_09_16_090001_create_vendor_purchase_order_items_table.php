<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Vendor PO stores items, quantity, cost, terms, due date, delivery date, notes, and attachments." */
    public function up(): void
    {
        Schema::create('vendor_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_purchase_order_items');
    }
};
