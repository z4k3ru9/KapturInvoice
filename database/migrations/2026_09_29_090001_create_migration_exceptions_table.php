<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 07: "Quarantine uncertain mappings and missing financial data
     * ... Import confidently mapped historical credits as read-only
     * records that reduce the applicable balance. Quarantine uncertain
     * credits and exclude them from confirmed balances until Owner
     * review." A quarantined source row still gets imported (never
     * invented/skipped silently) but is flagged here for review instead
     * of counted toward confirmed balances/reconciliation.
     *
     * Idempotency key per Specs.md ("source system + version + company +
     * entity type + source ID") is `company_id` + `entity_type` +
     * `source_id` — enforced below so re-running an import never
     * duplicates the same exception.
     */
    public function up(): void
    {
        Schema::create('migration_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('source_id');
            $table->string('severity')->default('medium');
            $table->text('reason');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['company_id', 'entity_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_exceptions');
    }
};
