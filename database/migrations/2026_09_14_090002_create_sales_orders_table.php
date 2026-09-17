<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The "Job" aggregate (docs/rebuild/specs/03-sales-and-job/Specs.md):
     * a NEW aggregate, never an extension of the legacy
     * `projects`/`tasks` tables — those stay
     * frozen and hidden from launch navigation (see
     * App\Models\Project's docblock). A sales order can only be created
     * from an already-Accepted quotation (see
     * App\Actions\Sales\CreateSalesOrderFromQuotation) — `quotation_id`
     * is therefore required, not nullable.
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained();

            $table->string('number')->nullable();
            $table->string('status')->default('draft'); // App\Enums\SalesOrderStatus

            // The job's currently-approved billable value: the accepted
            // quotation's total, plus the sum of every *approved*
            // App\Models\JobVariation since (FINALIZED-DECISIONS.md §4).
            // Milestone approval validates against this, not against the
            // frozen quotation total alone.
            $table->decimal('approved_value', 15, 2)->default(0);

            // Immutable copy of the accepted quotation's header fields at
            // the moment the job was created — "Preserve accepted
            // quotation values as the job source snapshot" (Specs.md).
            // Item-level snapshot lives in `sales_order_items`.
            $table->json('source_snapshot')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('operational_closed_at')->nullable();
            $table->timestamp('financial_closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

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
        Schema::dropIfExists('sales_orders');
    }
};
