<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Job types at launch are goods, installation, and service." —
     * FINALIZED-DECISIONS.md §10. Backfills existing rows from the
     * already-recorded `requires_handover` flag (false -> goods, true ->
     * installation — 'service' cannot be reconstructed from history, so no
     * existing row is backfilled as one) rather than a single blanket
     * value, so this migration never silently changes an existing job's
     * observed handover requirement.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('job_type')->default('installation')->after('requires_handover');
        });

        DB::table('sales_orders')->where('requires_handover', false)->update(['job_type' => 'goods']);
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('job_type');
        });
    }
};
