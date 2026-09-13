<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Support overrun, out-of-scope work, and item substitutions only
     * through Owner/Admin approval with reason. Preserve original approved
     * values and variation history" (Specs.md). Rows are append-only —
     * App\Actions\Sales\ApproveJobVariation never updates or deletes a
     * prior row, it only inserts a new one and advances the owning job's
     * `approved_value`.
     */
    public function up(): void
    {
        Schema::create('job_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type'); // App\Enums\JobVariationType
            $table->text('reason');
            $table->decimal('amount', 15, 2); // signed delta applied to the job's approved value
            $table->decimal('value_before', 15, 2);
            $table->decimal('value_after', 15, 2);
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_variations');
    }
};
