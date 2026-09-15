<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Job types at launch are goods, installation, and service." —
     * FINALIZED-DECISIONS.md §10. Selected here, before a Job even exists,
     * so App\Actions\Sales\CreateSalesOrderFromQuotation can copy it onto
     * the Job and derive `requires_handover` from it. Defaults to
     * 'installation' — the same conservative default `sales_orders.
     * requires_handover` already used for every job before this column
     * existed.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('job_type')->default('installation')->after('pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('job_type');
        });
    }
};
