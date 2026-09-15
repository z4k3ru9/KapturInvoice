<?php

namespace App\Actions\Procurement;

use App\Enums\CompanyRole;
use App\Enums\VendorPaymentStatus;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\AuditLogger;
use App\Services\Procurement\RecalculateVendorBillPayments;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Vendor payments use a parallel immutable event model: proof is
 * required before verification... cleared-cheque handling..." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Mirrors
 * App\Actions\Receivables\VerifyCustomerPayment: reuses
 * CompanyRole::paymentVerificationRoles() (Owner/Admin/Accountant) since
 * verifying a vendor payment is the same tier of billing-adjacent
 * privileged action as verifying a customer one, and a cheque payment
 * stays Pending until cleared.
 */
class VerifyVendorPayment
{
    public function __construct(
        private AuditLogger $auditLogger,
        private RecalculateVendorBillPayments $recalculator,
    ) {}

    public function verify(VendorPayment $payment, User $verifier, ?DateTimeInterface $chequeClearedAt = null): VendorPayment
    {
        if (! $verifier->hasCompanyRole($payment->company, ...CompanyRole::paymentVerificationRoles())) {
            throw new RuntimeException('Only Accountant, Admin, or Owner may verify a vendor payment.');
        }

        if ($payment->status !== VendorPaymentStatus::Pending) {
            throw new RuntimeException('Only a pending vendor payment can be verified.');
        }

        if (blank($payment->proof_path)) {
            throw new RuntimeException('A proof of payment upload is required before a vendor payment can be verified.');
        }

        if ($payment->method === 'cheque') {
            if (blank($payment->cheque_cleared_at) && $chequeClearedAt === null) {
                throw new RuntimeException('A cheque must clear before it can be verified.');
            }

            if ($chequeClearedAt !== null) {
                $payment->forceFill(['cheque_cleared_at' => $chequeClearedAt]);
            }
        }

        DB::transaction(function () use ($payment, $verifier) {
            $payment->forceFill([
                'status' => VendorPaymentStatus::Verified,
                'verified_at' => now(),
                'verified_by_user_id' => $verifier->id,
            ])->save();

            $this->recalculator->recalculate($payment->vendorBill);
        });

        $this->auditLogger->record(
            $payment->company,
            'vendor_payment.verified',
            $payment,
            ['status' => VendorPaymentStatus::Pending->value],
            ['status' => VendorPaymentStatus::Verified->value],
        );

        return $payment->fresh();
    }
}
