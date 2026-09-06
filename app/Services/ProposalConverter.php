<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Proposal;
use RuntimeException;

/**
 * Turns an accepted Proposal into a real, billable Invoice — mirrors
 * App\Services\InvoiceDuplicator::convertQuoteToInvoice() but starting
 * from a Proposal's single lump-sum `amount` rather than an existing set
 * of invoice items, since a Proposal isn't itself a row on `invoices`
 * (see docs/filament-admin-layout-design.md §2.7).
 */
class ProposalConverter
{
    public function __construct(private DocumentNumberGenerator $numberGenerator) {}

    public function convertToInvoice(Proposal $proposal): Invoice
    {
        if ($proposal->invoice_id) {
            throw new RuntimeException('This proposal has already been converted to an invoice.');
        }

        if (! $proposal->client_id) {
            throw new RuntimeException('This proposal has no client to bill — assign one before converting.');
        }

        $invoice = Invoice::create([
            'company_id' => $proposal->company_id,
            'client_id' => $proposal->client_id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => $this->numberGenerator->next($proposal->company, 'invoice'),
            'invoice_date' => now()->toDateString(),
            'public_notes' => $proposal->title,
        ]);

        $invoice->items()->create([
            'title' => $proposal->title,
            'quantity' => 1,
            'unit_cost' => $proposal->amount,
            'sort_order' => 0,
        ]);

        app(InvoiceTotalsCalculator::class)->recalculate($invoice);

        $proposal->forceFill(['invoice_id' => $invoice->id])->save();

        return $invoice;
    }
}
