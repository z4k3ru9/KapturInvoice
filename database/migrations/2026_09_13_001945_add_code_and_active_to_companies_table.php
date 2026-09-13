<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `code` is the short company code used in the new
     * `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` numbering format (see
     * App\Services\DocumentNumberGenerator and
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §1/§2) — distinct from the
     * legacy `invoice_prefix`/etc. columns, which stay untouched so
     * already-issued historical numbers remain exactly as issued.
     * `codes_locked_at` is set the first time this company's numbering is
     * ever allocated (see DocumentNumberGenerator::next()), after which the
     * code is no longer editable. `is_active` lets a company be disabled as
     * a whole (rejected by domain resolution and Filament tenant access)
     * without deleting its data.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('slug');
            $table->boolean('is_active')->default(true)->after('code');
            $table->timestamp('codes_locked_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['code', 'is_active', 'codes_locked_at']);
        });
    }
};
