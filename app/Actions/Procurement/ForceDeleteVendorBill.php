<?php

namespace App\Actions\Procurement;

use App\Enums\VendorBillStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Draft, never-paid Vendor Bill row — see
 * App\Actions\Billing\ForceDeleteInvoice's docblock for the shared
 * rationale.
 *
 * Ported from `App\Filament\Resources\VendorBills\Tables\
 * VendorBillsTable::isSafeToForceDelete()` (dropped, unreplaced, when
 * Filament was removed).
 */
class ForceDeleteVendorBill
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /** Safe iff: Draft status, and no payment was ever recorded against it. */
    public static function isSafeToForceDelete(VendorBill $vendorBill): bool
    {
        return $vendorBill->status === VendorBillStatus::Draft
            && ! $vendorBill->payments()->exists();
    }

    public function forceDelete(VendorBill $vendorBill, User $actor): void
    {
        if (! $actor->can('forceDelete', $vendorBill)) {
            throw new RuntimeException('Only the company Owner may permanently delete a vendor bill.');
        }

        if (! self::isSafeToForceDelete($vendorBill)) {
            throw new RuntimeException('Only a Draft vendor bill with no payments can be permanently deleted.');
        }

        DB::transaction(function () use ($vendorBill) {
            $this->auditLogger->record(
                $vendorBill->company,
                'vendor_bill.force_deleted',
                $vendorBill,
                $vendorBill->getAttributes(),
                [],
            );

            $vendorBill->forceDelete();
        });
    }
}
