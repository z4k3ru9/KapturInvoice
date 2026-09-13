<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Period soft lock (docs/rebuild/specs/01-company-foundation/Specs.md:
     * "Add period soft locks; Owner/Accountant reopening requires reason
     * and audit event"). `period_locked_through` is the last date treated
     * as closed; App\Services\PeriodLockService is the only writer.
     * Backdating/creating documents dated on or before this date is
     * blocked until an Owner/Accountant reopens it with a reason (audited).
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->date('period_locked_through')->nullable()->after('portal_require_signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('period_locked_through');
        });
    }
};
