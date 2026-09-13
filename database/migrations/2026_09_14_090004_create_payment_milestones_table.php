<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Support direct full payment or custom milestones by amount,
     * percentage, description, and due date" (Specs.md). A milestone's
     * `amount` is always the resolved nominal value — for a
     * percentage-based milestone it's computed from the job's
     * `approved_value` at the moment it's saved (see
     * App\Models\PaymentMilestone), so the sum-of-milestones check
     * (FINALIZED-DECISIONS.md §4: "approved milestone amounts must equal
     * the current approved job value") never has to re-resolve
     * percentages itself.
     */
    public function up(): void
    {
        Schema::create('payment_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();

            $table->string('type')->default('custom'); // App\Enums\MilestoneType
            $table->string('description')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('is_percentage')->default(false);
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_milestones');
    }
};
