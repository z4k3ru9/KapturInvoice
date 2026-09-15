<?php

namespace App\Actions\Receivables;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Receivables\RecalculateInvoiceReceivables;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Payment corrections, rejected payments, and reversals preserve the
 * original payment and receipt history." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. The payment itself moves
 * to PaymentStatus::Reversed but is never deleted; any receipt already
 * issued for it is untouched; and its PaymentAllocation rows are left in
 * place as history — App\Services\Receivables\RecalculateInvoiceReceivables
 * already excludes non-Verified payments from its sum, so the affected
 * invoices' balances fall back down once recalculated below.
 */
class ReverseCustomerPayment
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function reverse(Payment $payment, string $reason, User $actor): Payment
    {
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Verified], true)) {
            throw new RuntimeException('Only a pending or verified payment can be reversed.');
        }

        $affectedInvoiceIds = $payment->allocations()->where('is_active', true)->pluck('invoice_id')->unique()->all();

        DB::transaction(function () use ($payment, $reason, $actor) {
            $payment->forceFill(['status' => PaymentStatus::Reversed])->save();

            PaymentReversal::create([
                'payment_id' => $payment->id,
                'reason' => $reason,
                'reversed_by_user_id' => $actor->id,
                'reversed_at' => now(),
            ]);
        });

        foreach ($affectedInvoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);

            if ($invoice) {
                app(RecalculateInvoiceReceivables::class)->recalculate($invoice);
            }
        }

        $this->auditLogger->record(
            $payment->company,
            'payment.reversed',
            $payment,
            [],
            [],
            $reason,
        );

        return $payment->fresh();
    }
}
