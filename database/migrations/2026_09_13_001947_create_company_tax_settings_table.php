<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per company, split out from `companies`/`company_settings` so
     * tax behavior is configuration-driven per
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §3: Company A is
     * non-tax (tax_enabled=false), Company B is
     * tax-enabled with the approved 12% PPN / 11-12 DPP Nilai Lain
     * calculation. The full tax calculation engine lands in Phase 04
     * (docs/rebuild/specs/04-billing-and-receivables); this table only
     * scaffolds the per-company configuration Phase 01 needs to express
     * "is this company tax-enabled at all."
     */
    public function up(): void
    {
        Schema::create('company_tax_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('tax_enabled')->default(false);
            $table->string('default_tax_mode')->nullable(); // 'exclusive' | 'inclusive'
            $table->decimal('standard_tax_rate', 5, 2)->nullable(); // e.g. 12.00
            $table->unsignedTinyInteger('dpp_factor_numerator')->nullable(); // e.g. 11
            $table->unsignedTinyInteger('dpp_factor_denominator')->nullable(); // e.g. 12
            $table->json('report_config')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_tax_settings');
    }
};
