<?php

namespace App\Actions\Procurement;

use App\Enums\VendorPurchaseOrderStatus;
use App\Models\User;
use App\Models\VendorPurchaseOrder;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Draft, never-billed Vendor Purchase Order row —
 * see App\Actions\Billing\ForceDeleteInvoice's docblock for the shared
 * rationale.
 *
 * Ported from `App\Filament\Resources\VendorPurchaseOrders\Tables\
 * VendorPurchaseOrdersTable::isSafeToForceDelete()` (dropped, unreplaced,
 * when Filament was removed).
 */
class ForceDeleteVendorPurchaseOrder
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /** Safe iff: Draft status, and no bill was ever recorded against it. */
    public static function isSafeToForceDelete(VendorPurchaseOrder $vendorPurchaseOrder): bool
    {
        return $vendorPurchaseOrder->status === VendorPurchaseOrderStatus::Draft
            && ! $vendorPurchaseOrder->bills()->exists();
    }

    public function forceDelete(VendorPurchaseOrder $vendorPurchaseOrder, User $actor): void
    {
        if (! $actor->can('forceDelete', $vendorPurchaseOrder)) {
            throw new RuntimeException('Only the company Owner may permanently delete a vendor purchase order.');
        }

        if (! self::isSafeToForceDelete($vendorPurchaseOrder)) {
            throw new RuntimeException('Only a Draft vendor purchase order with no bills can be permanently deleted.');
        }

        DB::transaction(function () use ($vendorPurchaseOrder) {
            $this->auditLogger->record(
                $vendorPurchaseOrder->company,
                'vendor_purchase_order.force_deleted',
                $vendorPurchaseOrder,
                $vendorPurchaseOrder->getAttributes(),
                [],
            );

            $vendorPurchaseOrder->forceDelete();
        });
    }
}
