<?php

namespace App\Actions\Receivables;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ReceiptAmendment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Receivables\RecalculateInvoiceReceivables;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "If an allocation changes after the receipt is issued, preserve the
 * original receipt snapshot and create a numbered Receipt Amendment /
 * Allocation Adjustment linked to the same payment event." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Never touches the
 * original Receipt row's own `number`/`issued_at` — only records a
 * before/after allocation snapshot linked to it.
 */
class AmendPaymentAllocation
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param  array<int, float|string>  $newAllocations  invoice_id => amount */
    public function amend(Payment $payment, array $newAllocations, string $reason, User $actor): ReceiptAmendment
    {
        $receipt = $payment->receipt;

        if (! $receipt) {
            throw new RuntimeException('Only an already-receipted payment can have its allocation amended — use AllocateCustomerPayment instead.');
        }

        $invoices = Invoice::query()->whereIn('id', array_keys($newAllocations))->get()->keyBy('id');

        foreach ($newAllocations as $invoiceId => $amount) {
            $invoice = $invoices->get($invoiceId);

            if (! $invoice || $invoice->company_id !== $payment->company_id || $invoice->client_id !== $payment->client_id) {
                throw new RuntimeException("Invoice #{$invoiceId} does not belong to this payment's client/company.");
            }
        }

        if (array_sum(array_map('floatval', $newAllocations)) > (float) $payment->amount + 0.0001) {
            throw new RuntimeException('The allocation total cannot exceed the payment amount.');
        }

        $before = $payment->allocations()->where('is_active', true)->get(['invoice_id', 'amount'])->toArray();
        $previouslyAllocatedInvoiceIds = array_map(fn ($row) => $row['invoice_id'], $before);

        $after = [];
        foreach ($newAllocations as $invoiceId => $amount) {
            $after[] = ['invoice_id' => $invoiceId, 'amount' => $amount];
        }

        $amendment = DB::transaction(function () use ($payment, $newAllocations, $receipt, $reason, $actor, $before, $after) {
            $payment->allocations()->where('is_active', true)->update(['is_active' => false]);

            foreach ($newAllocations as $invoiceId => $amount) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'is_active' => true,
                ]);
            }

            return ReceiptAmendment::create([
                'receipt_id' => $receipt->id,
                'reason' => $reason,
                'allocations_before' => $before,
                'allocations_after' => $after,
                'amended_by_user_id' => $actor->id,
                'amended_at' => now(),
            ]);
        });

        $touchedInvoiceIds = array_unique([...$previouslyAllocatedInvoiceIds, ...array_keys($newAllocations)]);

        foreach ($touchedInvoiceIds as $invoiceId) {
            $invoice = $invoices->get($invoiceId) ?? Invoice::find($invoiceId);

            if ($invoice) {
                app(RecalculateInvoiceReceivables::class)->recalculate($invoice);
            }
        }

        $this->auditLogger->record(
            $payment->company,
            'payment.allocation_amended',
            $receipt,
            $before,
            $after,
            $reason,
        );

        return $amendment;
    }
}
