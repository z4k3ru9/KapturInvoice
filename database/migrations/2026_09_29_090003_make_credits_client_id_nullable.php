<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Required by the Phase 07 credit-quarantine flow this cherry-pick adds
     * to `ImportInvoiceNinjaV4::importCredits()`/`ImportInvoiceNinjaV5::
     * importCredits()` (docs/rebuild/specs/07-migration-and-cutover/Specs.md:
     * "Import confidently mapped historical credits as read-only records
     * that reduce the applicable balance. Quarantine uncertain credits and
     * exclude them from confirmed balances until Owner review."). A legacy
     * credit whose source client_id never mapped to an imported Client is
     * still imported in full (never invented or silently dropped) with
     * `client_id = null`, flagged via a `MigrationException` instead.
     *
     * `credits.client_id` was `NOT NULL` (`2026_09_05_095231_create_credits_
     * _table.php`) before this Phase 07 slice existed on `main` — the
     * origin/claude/phase-07-migration-cutover branch this was cherry-picked
     * from already had this same column as `nullable()` on its own copy of
     * that same migration file (it forked before `main`'s version was
     * hardened to NOT NULL, and the two diverged on this one column). Kept
     * as its own additive migration here rather than editing the original
     * (already-applied) migration file.
     */
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable(false)->change();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }
};
