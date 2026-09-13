<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Goods-only jobs may close operationally after delivery. Installation/
 * service jobs require handover before operational closure." —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md. Read literally:
 * a goods-only job (`requires_handover = false`) needs only at least one
 * recorded Delivery Order, not a fully-delivered job
 * (App\Models\SalesOrder::isFullyDelivered()) — that stricter check gates
 * App\Actions\Delivery\CompleteHandover instead. Operational closure is
 * independent of financial closure — see
 * App\Actions\Sales\CloseJobFinancially and
 * App\Models\SalesOrder::isFullyClosed().
 */
class CloseJobOperationally
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function close(SalesOrder $salesOrder): SalesOrder
    {
        if ($salesOrder->operational_closed_at !== null) {
            throw new RuntimeException('This job is already operationally closed.');
        }

        if ($salesOrder->requires_handover) {
            if (! $salesOrder->handoverReports()->exists()) {
                throw new RuntimeException('Operational closure requires a completed handover for this job.');
            }
        } elseif (! $salesOrder->deliveryOrders()->exists()) {
            throw new RuntimeException('Operational closure requires at least one recorded delivery for this job.');
        }

        $salesOrder->forceFill(['operational_closed_at' => now()])->save();

        $this->auditLogger->record(
            $salesOrder->company,
            'sales_order.operationally_closed',
            $salesOrder,
            ['operational_closed_at' => null],
            ['operational_closed_at' => $salesOrder->operational_closed_at],
        );

        return $salesOrder->fresh();
    }
}
