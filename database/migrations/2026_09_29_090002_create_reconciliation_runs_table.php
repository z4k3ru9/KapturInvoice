<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 07: "Reconcile counts, line totals, discounts, tax, payments,
     * allocations, open balances, vendor due balances, source numbers/
     * dates, and exceptions per company." `metrics` is the full structured
     * comparison (source vs. imported, per entity type) — persisted
     * rather than only printed to the console, so a cutover checkpoint
     * can point at a specific run instead of a screen of scrollback.
     */
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('migration_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('clean');
            $table->json('metrics');
            $table->timestamp('run_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
