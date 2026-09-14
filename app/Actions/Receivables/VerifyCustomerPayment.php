<?php

namespace App\Actions\Receivables;

use App\Enums\CompanyRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentVerificationEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Receivables\RecalculateInvoiceReceivables;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Accountant and higher verify payments." — "A received cheque stays
 * pending until bank clearance. It is verified and receipted only after
 * clearance." — docs/rebuild/specs/04-billing-and-receivables/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Mirrors
 * App\Actions\Sales\ApproveJobVariation's role-check mechanism against
 * CompanyRole::paymentVerificationRoles().
 *
 * A Codex review finding on PR #4: the "Allocate" table action is visible
 * as soon as a payment has no receipt yet (App\Actions\Receivables\
 * AllocateCustomerPayment doesn't require Verified status), so a payment
 * can be allocated to invoices while still Pending — at which point
 * RecalculateInvoiceReceivables's own `Verified`-only sum naturally
 * excludes it, leaving those invoices' amount_paid/balance/status stale
 * from the moment this payment later becomes Verified until someone
 * happens to touch the allocation again. Recalculating every currently
 * active-allocated invoice here closes that gap.
 */
class VerifyCustomerPayment
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function verify(Payment $payment, User $verifier, ?DateTimeInterface $chequeClearedAt = null): Payment
    {
        if (! $verifier->hasCompanyRole($payment->company, ...CompanyRole::paymentVerificationRoles())) {
            throw new RuntimeException('Only Accountant, Admin, or Owner may verify a payment.');
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw new RuntimeException('Only a pending payment can be verified.');
        }

        if ($payment->method === PaymentMethod::Cheque->value) {
            if (blank($payment->cheque_cleared_at) && $chequeClearedAt === null) {
                throw new RuntimeException('A cheque must clear before it can be verified.');
            }

            if ($chequeClearedAt !== null) {
                $payment->forceFill(['cheque_cleared_at' => $chequeClearedAt]);
            }
        }

        DB::transaction(function () use ($payment, $verifier) {
            $payment->forceFill([
                'status' => PaymentStatus::Verified,
                'verified_at' => now(),
                'verified_by_user_id' => $verifier->id,
            ])->save();

            PaymentVerificationEvent::create([
                'payment_id' => $payment->id,
                'verified_by_user_id' => $verifier->id,
                'status' => 'verified',
                'occurred_at' => now(),
            ]);

            $invoiceIds = $payment->allocations()->where('is_active', true)->pluck('invoice_id');

            foreach ($invoiceIds as $invoiceId) {
                if ($invoice = Invoice::find($invoiceId)) {
                    app(RecalculateInvoiceReceivables::class)->recalculate($invoice);
                }
            }
        });

        $this->auditLogger->record(
            $payment->company,
            'payment.verified',
            $payment,
            ['status' => PaymentStatus::Pending->value],
            ['status' => PaymentStatus::Verified->value],
        );

        return $payment->fresh();
    }
}
