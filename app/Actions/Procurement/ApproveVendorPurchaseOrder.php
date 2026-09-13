<?php

namespace App\Actions\Procurement;

use App\Enums\VendorPurchaseOrderStatus;
use App\Models\VendorPurchaseOrder;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * Approves a Vendor PO, unlocking it as a real payment ceiling for bills
 * raised against it (`VendorPurchaseOrder::paymentCeiling()`) — per
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md. The PO's own
 * `total` is expected to already reflect the sum of its items (kept in
 * sync by a Filament relation-manager afterSave hook, not this action) —
 * approval never recomputes or edits it.
 */
class ApproveVendorPurchaseOrder
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(VendorPurchaseOrder $po): VendorPurchaseOrder
    {
        if (! $po->status->canTransitionTo(VendorPurchaseOrderStatus::Approved)) {
            throw new RuntimeException("A vendor purchase order in status [{$po->status->value}] cannot be approved.");
        }

        $po->forceFill([
            'status' => VendorPurchaseOrderStatus::Approved,
            'approved_at' => now(),
        ])->save();

        $this->auditLogger->record(
            $po->company,
            'vendor_purchase_order.approved',
            $po,
            ['status' => VendorPurchaseOrderStatus::Draft->value],
            ['status' => VendorPurchaseOrderStatus::Approved->value],
        );

        return $po->fresh();
    }
}
