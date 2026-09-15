<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "One vendor purchase may serve multiple jobs. Allocate shared cost
     * by explicit quantity or amount. Prevent allocation above source
     * line amount. Show unallocated remainder and exclude it from
     * confidently allocated margin." Append-only — a correction is a new
     * allocation row (possibly negative via a future reversal action),
     * never an edit of a prior one.
     */
    public function up(): void
    {
        Schema::create('job_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('method')->default('amount'); // amount|quantity
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_cost_allocations');
    }
};
