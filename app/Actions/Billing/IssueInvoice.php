<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceTaxSnapshot;
use App\Models\TaxRecap;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use App\Services\Tax\TaxCalculationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Issuance stores immutable tax snapshot and document snapshot." —
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md. Draft invoices
 * are first advanced to Approved (App\Enums\InvoiceStatus::allowedNextStates
 * only allows Draft -> Approved, not Draft -> Issued directly) before the
 * Approved -> Issued transition that actually calculates and freezes
 * totals via App\Services\Tax\TaxCalculationService.
 */
class IssueInvoice
{
    public function __construct(
        private TaxCalculationService $taxCalculator,
        private DocumentNumberGenerator $numberGenerator,
        private AuditLogger $auditLogger,
    ) {}

    public function issue(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Approved], true)) {
            throw new RuntimeException(
                "Invoice cannot be issued from status [{$invoice->status->value}]."
            );
        }

        $invoice->loadMissing(['items.product', 'company.taxSetting']);

        $lines = $invoice->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'discount' => $item->discount,
            'discount_is_percentage' => $item->discount_is_percentage,
            'tax_category' => $item->resolveTaxCategory(),
        ])->all();

        $result = $this->taxCalculator->calculate(
            $lines,
            $invoice->pricing_mode,
            $invoice->company->taxSetting,
            (float) $invoice->discount,
            (bool) $invoice->discount_is_percentage,
        );

        $number = $invoice->number;

        if (blank($number)) {
            $number = $this->numberGenerator->next($invoice->company, 'invoice');
        }

        return DB::transaction(function () use ($invoice, $result, $number) {
            if ($invoice->status === InvoiceStatus::Draft) {
                if (! $invoice->status->canTransitionTo(InvoiceStatus::Approved)) {
                    throw new RuntimeException(
                        "Invoice cannot be issued from status [{$invoice->status->value}]."
                    );
                }

                $invoice->forceFill([
                    'status' => InvoiceStatus::Approved,
                    'approved_at' => now(),
                ])->save();
            }

            if (! $invoice->status->canTransitionTo(InvoiceStatus::Issued)) {
                throw new RuntimeException(
                    "Invoice cannot be issued from status [{$invoice->status->value}]."
                );
            }

            $total = $result['total'];
            $balance = round($total - (float) $invoice->amount_paid, 2);

            $invoice->forceFill([
                'number' => $number,
                'subtotal' => $result['subtotal'],
                'tax_total' => $result['tax_total'],
                'total' => $total,
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'balance' => $balance,
            ])->save();

            $itemsSnapshot = $invoice->items->values()->map(function ($item, $index) use ($result) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'quantity' => (string) $item->quantity,
                    'unit_cost' => (string) $item->unit_cost,
                    'discount' => (string) $item->discount,
                    'discount_is_percentage' => $item->discount_is_percentage,
                    'tax_category' => $item->resolveTaxCategory()->value,
                    'breakdown' => $result['lines'][$index] ?? null,
                ];
            })->all();

            InvoiceTaxSnapshot::create([
                'invoice_id' => $invoice->id,
                'pricing_mode' => $invoice->pricing_mode->value,
                'subtotal' => $result['subtotal'],
                'discount_total' => $result['discount_total'],
                'taxable_base_total' => $result['taxable_base_total'],
                'tax_total' => $result['tax_total'],
                'pre_round_total' => $result['pre_round_total'],
                'rounding_adjustment' => $result['rounding_adjustment'],
                'total' => $result['total'],
                'items_snapshot' => $itemsSnapshot,
                'captured_at' => now(),
            ]);

            if (($invoice->company->taxSetting?->tax_enabled ?? false) && $result['tax_total'] > 0) {
                TaxRecap::create([
                    'invoice_id' => $invoice->id,
                    'number' => $this->numberGenerator->next($invoice->company, 'tax_recap'),
                    'reporting_period' => $invoice->invoice_date?->format('Y-m') ?? now()->format('Y-m'),
                    'manual_entry_status' => 'pending',
                ]);
            }

            $this->auditLogger->record(
                $invoice->company,
                'invoice.issued',
                $invoice,
                [],
                ['number' => $invoice->number, 'total' => (string) $invoice->total],
            );

            return $invoice->fresh(['items', 'taxSnapshot', 'taxRecap']);
        });
    }
}
