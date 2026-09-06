<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;

/**
 * Backs the "Convert to invoice" action on Quotes and the "Generate now"
 * action on Recurring Invoices (see docs/filament-admin-layout-design.md
 * §2.1) — both need the same thing: clone an invoice's shared fields plus
 * its items/taxes into a new invoice row, then recompute totals.
 */
class InvoiceDuplicator
{
    public function __construct(protected DocumentNumberGenerator $numberGenerator) {}

    public function convertQuoteToInvoice(Invoice $quote): Invoice
    {
        $invoice = $this->cloneSharedFields($quote);
        $invoice->type = InvoiceType::Invoice;
        $invoice->status = InvoiceStatus::Draft;
        $invoice->converted_from_quote_id = $quote->id;
        $invoice->number = $this->numberGenerator->next($quote->company, 'invoice');
        $invoice->save();

        $this->cloneItems($quote, $invoice);

        app(InvoiceTotalsCalculator::class)->recalculate($invoice);

        return $invoice;
    }

    public function generateRecurringInstance(Invoice $template): Invoice
    {
        $invoice = $this->cloneSharedFields($template);
        $invoice->type = InvoiceType::Invoice;
        $invoice->status = InvoiceStatus::Draft;
        $invoice->is_recurring = false;
        $invoice->recurring_template_id = $template->id;
        $invoice->invoice_date = now()->toDateString();
        $invoice->number = $this->numberGenerator->next($template->company, 'invoice');
        $invoice->save();

        $this->cloneItems($template, $invoice);

        app(InvoiceTotalsCalculator::class)->recalculate($invoice);

        $template->forceFill(['recurring_last_sent_at' => now()])->saveQuietly();

        return $invoice;
    }

    protected function cloneSharedFields(Invoice $source): Invoice
    {
        $invoice = new Invoice([
            'company_id' => $source->company_id,
            'client_id' => $source->client_id,
            'po_number' => $source->po_number,
            'due_date' => $source->due_date,
            'currency_code' => $source->currency_code,
            'exchange_rate' => $source->exchange_rate,
            'discount' => $source->discount,
            'discount_is_percentage' => $source->discount_is_percentage,
            'terms' => $source->terms,
            'public_notes' => $source->public_notes,
            'footer' => $source->footer,
        ]);
        $invoice->invoice_date = now()->toDateString();

        return $invoice;
    }

    protected function cloneItems(Invoice $source, Invoice $target): void
    {
        $source->loadMissing('items.taxes');

        foreach ($source->items as $item) {
            $newItem = $target->items()->create([
                'product_id' => $item->product_id,
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'discount' => $item->discount,
                'discount_is_percentage' => $item->discount_is_percentage,
                'sort_order' => $item->sort_order,
            ]);

            foreach ($item->taxes as $tax) {
                $newItem->taxes()->create([
                    'tax_rate_id' => $tax->tax_rate_id,
                    'name' => $tax->name,
                    'rate' => $tax->rate,
                    'amount' => $tax->amount,
                ]);
            }
        }
    }
}
