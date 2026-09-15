<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two follow-up index gaps flagged (not fixed) by the earlier
     * Procurement/Delivery/Clients/Portal index audit
     * (2026_09_27_090000_...), both confirmed missing via
     * Schema::getIndexes() against a fresh migrate:fresh:
     *
     * 1. `invoices.invoice_date` / `payments.payment_date` — filtered on
     *    every App\Support\Dashboard\RevenueBuckets::forPeriod() call
     *    (the dashboard revenue chart/table), now a flat 2-query range
     *    scan per period instead of the original per-bucket loop, but
     *    still an unindexed range filter over every company's rows.
     * 2. `company_id` on the tables the prior audit's task boundary
     *    excluded (Sales/Billing/Dashboard/Reports/Settings component
     *    group): payments, payment_gateways, projects, proposals,
     *    proposal_snippets, proposal_templates, reminder_suppressions,
     *    tasks, task_statuses — every `BelongsToCompany` query on these
     *    filters by `company_id` with no supporting index at all.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('invoice_date');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date');
            $table->index('company_id');
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('proposal_snippets', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('proposal_templates', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('reminder_suppressions', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('task_statuses', function (Blueprint $table) {
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['company_id']);
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('proposal_snippets', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('proposal_templates', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('reminder_suppressions', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('task_statuses', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });
    }
};
