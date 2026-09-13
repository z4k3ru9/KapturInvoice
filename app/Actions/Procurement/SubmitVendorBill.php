<?php

namespace App\Actions\Procurement;

use App\Enums\VendorBillStatus;
use App\Models\VendorBill;
use RuntimeException;

/**
 * The `Draft -> Submitted` step of the bill lifecycle described in
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md ("Vendor bills
 * flow through Draft, Submitted, Approved, Partially Paid, and Paid").
 */
class SubmitVendorBill
{
    public function submit(VendorBill $bill): VendorBill
    {
        if (! $bill->status->canTransitionTo(VendorBillStatus::Submitted)) {
            throw new RuntimeException("A vendor bill in status [{$bill->status->value}] cannot be submitted.");
        }

        $bill->forceFill(['status' => VendorBillStatus::Submitted])->save();

        return $bill->fresh();
    }
}
