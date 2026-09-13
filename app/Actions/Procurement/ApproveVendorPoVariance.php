<?php

namespace App\Actions\Procurement;

use App\Enums\CompanyRole;
use App\Models\User;
use App\Models\VendorPoVariance;
use App\Models\VendorPurchaseOrder;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "A vendor bill can exceed its approved Vendor PO only as a recorded
 * variance. Payment above the PO total is blocked until Admin or Owner
 * approves the variance with a reason; the original PO remains
 * immutable." — docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Mirrors
 * App\Actions\Sales\ApproveJobVariation's role-check mechanism against
 * CompanyRole::vendorPoVarianceApprovalRoles(). Never edits
 * `$po->total` — the variance only raises the effective payment ceiling
 * computed by `VendorPurchaseOrder::paymentCeiling()`.
 */
class ApproveVendorPoVariance
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(VendorPurchaseOrder $po, User $approver, string $reason, float $amount): VendorPoVariance
    {
        if (! $approver->hasCompanyRole($po->company, ...CompanyRole::vendorPoVarianceApprovalRoles())) {
            throw new RuntimeException('Only Owner/Admin may approve a vendor PO variance.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('A vendor PO variance amount must be greater than zero.');
        }

        $variance = VendorPoVariance::create([
            'vendor_purchase_order_id' => $po->id,
            'approved_by_user_id' => $approver->id,
            'reason' => $reason,
            'amount' => $amount,
            'approved_at' => now(),
        ]);

        $this->auditLogger->record(
            $po->company,
            'vendor_po_variance.approved',
            $po,
            [],
            ['amount' => $amount, 'payment_ceiling' => $po->fresh()->paymentCeiling()],
            $reason,
        );

        return $variance;
    }
}
