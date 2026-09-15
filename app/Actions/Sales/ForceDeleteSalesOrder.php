<?php

namespace App\Actions\Sales;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Draft Job (SalesOrder) with no downstream activity
 * whatsoever — see App\Actions\Billing\ForceDeleteInvoice's docblock for
 * the shared rationale.
 *
 * Ported from the equivalent table-level guard in the pre-TallStackUI
 * Filament admin (dropped, unreplaced, when Filament was removed).
 */
class ForceDeleteSalesOrder
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Safe iff: Draft status, never operationally/financially closed, and
     * no invoice, delivery order, handover report, service report, job
     * cost allocation, or variation was ever recorded against it.
     */
    public static function isSafeToForceDelete(SalesOrder $salesOrder): bool
    {
        return $salesOrder->status === SalesOrderStatus::Draft
            && $salesOrder->operational_closed_at === null
            && $salesOrder->financial_closed_at === null
            && ! $salesOrder->invoices()->exists()
            && ! $salesOrder->deliveryOrders()->exists()
            && ! $salesOrder->handoverReports()->exists()
            && ! $salesOrder->serviceReports()->exists()
            && ! $salesOrder->jobCostAllocations()->exists()
            && ! $salesOrder->variations()->exists();
    }

    public function forceDelete(SalesOrder $salesOrder, User $actor): void
    {
        if (! $actor->can('forceDelete', $salesOrder)) {
            throw new RuntimeException('Only the company Owner may permanently delete a job.');
        }

        if (! self::isSafeToForceDelete($salesOrder)) {
            throw new RuntimeException('Only a Draft job with no invoices, deliveries, handovers, service reports, job cost allocations, or variations can be permanently deleted.');
        }

        DB::transaction(function () use ($salesOrder) {
            $this->auditLogger->record(
                $salesOrder->company,
                'sales_order.force_deleted',
                $salesOrder,
                $salesOrder->getAttributes(),
                [],
            );

            $salesOrder->forceDelete();
        });
    }
}
