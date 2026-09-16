<?php

namespace App\Services\Receivables;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\PaymentAllocation;

/**
 * "No invoice balance is authoritative unless derived from allocations." —
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md acceptance
 * criteria. Recomputes `amount_paid`/`balance` from the sum of active
 * PaymentAllocation rows whose payment is PaymentStatus::Verified — never
 * from a running total kept on the invoice itself — and, when the invoice
 * is currently in one of the "billable" states (Issued/Partial/Overdue),
 * derives its status from that same sum rather than trusting whatever
 * status it already carries. Called by every Receivables action that
 * changes an allocation or a payment's verified/reversed state.
 */
class RecalculateInvoiceReceivables
{
    /** @var list<InvoiceStatus> */
    private const BILLABLE_STATES = [
        InvoiceStatus::Issued,
        InvoiceStatus::Partial,
        InvoiceStatus::Overdue,
    ];

    public function recalculate(Invoice $invoice): Invoice
    {
        $paid = round((float) PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where('is_active', true)
            ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Verified))
            ->sum('amount'), 2);

        $balance = round((float) $invoice->total - $paid, 2);

        $attributes = [
            'amount_paid' => $paid,
            'balance' => $balance,
        ];

        if (in_array($invoice->status, self::BILLABLE_STATES, true)) {
            $isPastDue = $invoice->due_date !== null && $invoice->due_date->isPast();

            $attributes['status'] = match (true) {
                $balance <= 0.0 => InvoiceStatus::Paid,
                // Overdue is itself a billable state (App\Console\Commands\
                // MarkInvoicesOverdue is the only writer that first sets it),
                // so it must be preserved/re-derived here rather than always
                // falling back to Issued/Partial — otherwise an unrelated
                // payment event on an already-Overdue invoice would silently
                // clear the marker before the next scheduled run had a
                // chance to re-set it.
                $isPastDue => InvoiceStatus::Overdue,
                $paid <= 0.0 => InvoiceStatus::Issued,
                default => InvoiceStatus::Partial,
            };
        }

        $invoice->forceFill($attributes)->saveQuietly();

        return $invoice->fresh();
    }
}
