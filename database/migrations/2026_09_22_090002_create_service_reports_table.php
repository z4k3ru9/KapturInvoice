<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Service Report (`SVR`, change request ratified 2026-09-14,
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §10) — one numbered
     * document per service visit on a job. Follows the Delivery Order/
     * Handover Report pattern (job-scoped, immutable once recorded) but
     * additionally carries its own Draft -> Submitted -> Approved
     * lifecycle (like VendorBill) because a report is drafted, then
     * approved separately, before it can count toward the service-job
     * handover gate.
     */
    public function up(): void
    {
        Schema::create('service_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->string('status')->default('draft');

            $table->date('service_date')->nullable();
            $table->foreignId('technician_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_technician_name')->nullable();

            $table->text('problem_reported')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('parts_used')->nullable();
            $table->string('result')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->string('customer_acknowledgement_name')->nullable();

            $table->json('snapshot')->nullable();
            $table->string('document_language')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reports');
    }
};
