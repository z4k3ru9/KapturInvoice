<?php

namespace App\Actions\Procurement;

use App\Enums\CompanyRole;
use App\Enums\VendorBillStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Vendor bills flow through Draft, Submitted, Approved, Partially Paid,
 * and Paid; Accountant and higher may self-approve at launch, with
 * explicit audit evidence." —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Mirrors
 * App\Actions\Receivables\VerifyCustomerPayment's role-check mechanism
 * against CompanyRole::vendorBillApprovalRoles(). Deliberately does NOT
 * check the bill against its Vendor PO's payment ceiling — a bill can be
 * approved even if it exceeds the PO (it still needs review), the
 * ceiling check belongs entirely to App\Actions\Procurement\
 * RecordVendorPayment.
 */
class ApproveVendorBill
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(VendorBill $bill, User $approver): VendorBill
    {
        if (! $approver->hasCompanyRole($bill->company, ...CompanyRole::vendorBillApprovalRoles())) {
            throw new RuntimeException('Only Accountant, Admin, or Owner may approve a vendor bill.');
        }

        if (! $bill->status->canTransitionTo(VendorBillStatus::Approved)) {
            throw new RuntimeException("A vendor bill in status [{$bill->status->value}] cannot be approved.");
        }

        $bill->forceFill([
            'status' => VendorBillStatus::Approved,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ])->save();

        $this->auditLogger->record(
            $bill->company,
            'vendor_bill.approved',
            $bill,
            ['status' => VendorBillStatus::Submitted->value],
            ['status' => VendorBillStatus::Approved->value],
        );

        return $bill->fresh();
    }
}
