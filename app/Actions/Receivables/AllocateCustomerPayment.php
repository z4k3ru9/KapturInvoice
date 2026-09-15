<?php

namespace App\Actions\Receivables;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Receivables\RecalculateInvoiceReceivables;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "One payment allocates across multiple invoices/jobs only within the
 * same client/company." "Partial payment and unallocated overpayment are
 * visible." — docs/rebuild/specs/04-billing-and-receivables/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Only legal before a
 * receipt is issued for the payment — once issued, use
 * App\Actions\Receivables\AmendPaymentAllocation instead, which preserves
 * the original receipt snapshot. Replaces (not stacks) any prior
 * allocation set, so calling this repeatedly pre-receipt is idempotent.
 */
class AllocateCustomerPayment
{
    /** @param  array<int, float|string>  $allocations  invoice_id => amount */
    public function allocate(Payment $payment, array $allocations): Payment
    {
        if ($payment->receipt()->exists()) {
            throw new RuntimeException('A receipt already exists for this payment — use AmendPaymentAllocation instead.');
        }

        $invoices = Invoice::query()->whereIn('id', array_keys($allocations))->get()->keyBy('id');

        foreach ($allocations as $invoiceId => $amount) {
            $invoice = $invoices->get($invoiceId);

            if (! $invoice || $invoice->company_id !== $payment->company_id || $invoice->client_id !== $payment->client_id) {
                throw new RuntimeException("Invoice #{$invoiceId} does not belong to this payment's client/company.");
            }
        }

        if (array_sum(array_map('floatval', $allocations)) > (float) $payment->amount + 0.0001) {
            throw new RuntimeException('The allocation total cannot exceed the payment amount.');
        }

        $previouslyAllocatedInvoiceIds = $payment->allocations()->where('is_active', true)->pluck('invoice_id')->all();

        DB::transaction(function () use ($payment, $allocations) {
            $payment->allocations()->where('is_active', true)->update(['is_active' => false]);

            foreach ($allocations as $invoiceId => $amount) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'is_active' => true,
                ]);
            }
        });

        $touchedInvoiceIds = array_unique([...$previouslyAllocatedInvoiceIds, ...array_keys($allocations)]);

        foreach ($touchedInvoiceIds as $invoiceId) {
            $invoice = $invoices->get($invoiceId) ?? Invoice::find($invoiceId);

            if ($invoice) {
                app(RecalculateInvoiceReceivables::class)->recalculate($invoice);
            }
        }

        return $payment->fresh();
    }
}
