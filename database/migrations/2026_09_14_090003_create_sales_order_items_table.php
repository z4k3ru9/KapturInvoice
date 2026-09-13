<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The job's own line-item snapshot, copied from `quotation_items` at
     * job-creation time (see App\Actions\Sales\CreateSalesOrderFromQuotation)
     * and never re-synced from the quotation afterward — "Preserve
     * accepted quotation values as the job source snapshot."
     * `quotation_item_id` is kept only for traceability back to the
     * source line, not as a live reference.
     */
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

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
        Schema::dropIfExists('sales_order_items');
    }
};
