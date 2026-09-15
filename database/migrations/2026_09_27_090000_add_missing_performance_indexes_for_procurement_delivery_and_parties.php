<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive missing-index fixes found during the N+1/index audit of
     * TallStackVendors/VendorBills/VendorBillForm/VendorPurchaseOrderForm/
     * Expenses/DeliveryOrders/DeliveryOrder/HandoverReports/Clients/
     * ClientDetail/ClientPortalInvitations/Documents/Products/
     * PriceListItems and the public portal pages. `foreignId()->constrained()`
     * does NOT add an index by itself (SQLite/MySQL both): a table only
     * has one where an earlier migration separately added `->unique([...])`
     * for a business rule (e.g. `unique(['company_id', 'number'])`) or a
     * deliberate `->index()`/`->unique()` call — every column below was
     * confirmed to have no supporting index at all via
     * `Schema::getIndexes()` against a fresh `migrate:fresh`, not assumed.
     *
     * Left out of this pass (real, but on tables owned by the explicitly
     * out-of-scope Sales/Billing/Dashboard/Reports/Settings component
     * group per this audit's task boundary): `payments.company_id`,
     * `payment_gateways.company_id`, `projects.company_id`,
     * `proposals.company_id`, `proposal_snippets.company_id`,
     * `proposal_templates.company_id`, `reminder_suppressions.company_id`,
     * `tasks.company_id`, `task_statuses.company_id` — flagged in the
     * audit report as a follow-up, not fixed here.
     */
    public function up(): void
    {
        // Company-scoped list pages that filter every query by
        // `company_id` with no supporting index at all
        // (TallStackVendors, TallStackExpenses, TallStackDocuments; the
        // category dropdown query on TallStackExpenses).
        Schema::table('vendors', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->index('company_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('company_id');
        });

        // TallStackVendorBills filters `status` and (via ?vendor= in the
        // URL) `vendor_id` on top of its `company_id` scope.
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->index('vendor_id');
            $table->index('status');
        });

        // Line-item/child tables looked up via a parent relation
        // (`$bill->items`, `$purchaseOrder->items`, `$item->jobCostAllocations`,
        // `$deliveryOrder->items`, `$salesOrder->items`) throughout
        // TallStackVendorBillForm/TallStackVendorPurchaseOrderForm/
        // TallStackDeliveryOrder — every one of these FK columns had no
        // index, so each relation load was a full table scan across
        // every company's line items.
        Schema::table('vendor_bill_items', function (Blueprint $table) {
            $table->index('vendor_bill_id');
        });

        Schema::table('vendor_purchase_order_items', function (Blueprint $table) {
            $table->index('vendor_purchase_order_id');
        });

        Schema::table('job_cost_allocations', function (Blueprint $table) {
            $table->index('vendor_bill_item_id');
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->index('delivery_order_id');
            $table->index('sales_order_item_id');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->index('sales_order_id');
        });

        // TallStackDeliveryOrders/TallStackHandoverReports search
        // (`whereHas('salesOrder', ...)`) and TallStackDeliveryOrder's own
        // "delivered to date" rollup (fixed alongside this migration)
        // join back to the job by `sales_order_id`, with no index.
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->index('sales_order_id');
        });

        Schema::table('handover_reports', function (Blueprint $table) {
            $table->index('sales_order_id');
        });

        // TallStackClientDetail's Portal Links relation manager
        // (`$client->portalLinks`) and TallStackClientPortalInvitations'
        // `whereHas('invoice', ...)` join both filter by an unindexed FK.
        Schema::table('portal_links', function (Blueprint $table) {
            $table->index('client_id');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->dropIndex(['vendor_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('vendor_bill_items', function (Blueprint $table) {
            $table->dropIndex(['vendor_bill_id']);
        });

        Schema::table('vendor_purchase_order_items', function (Blueprint $table) {
            $table->dropIndex(['vendor_purchase_order_id']);
        });

        Schema::table('job_cost_allocations', function (Blueprint $table) {
            $table->dropIndex(['vendor_bill_item_id']);
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropIndex(['delivery_order_id']);
            $table->dropIndex(['sales_order_item_id']);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropIndex(['sales_order_id']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropIndex(['sales_order_id']);
        });

        Schema::table('handover_reports', function (Blueprint $table) {
            $table->dropIndex(['sales_order_id']);
        });

        Schema::table('portal_links', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });
    }
};
