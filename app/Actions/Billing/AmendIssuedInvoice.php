<?php

namespace App\Actions\Billing;

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PricingMode;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Corrections use amendment or void-and-reissue with reason and new
 * number." — docs/rebuild/specs/FINALIZED-DECISIONS.md §2. "Amend issued
 * document: Admin/Owner for document edits; preserve original." —
 * docs/rebuild/Specs.md §10, enforced here via
 * App\Enums\CompanyRole::documentAmendmentRoles() rather than only at the
 * Filament table-action layer. The original invoice is never edited
 * beyond its status flipping to Amended — its own number, totals, and tax
 * snapshot stay exactly as issued. The correction is a brand-new Invoice
 * row, numbered from the separate `invoice_amendment` (`INV-A`) sequence,
 * issued through App\Actions\Billing\IssueInvoice so it gets its own tax
 * snapshot/recap like any other issued invoice (Admin/Owner already
 * satisfies IssueInvoice's own broader Accountant-and-higher check).
 *
 * Scoped to a plain `type = InvoiceType::Invoice` row here, same guard as
 * App\Actions\Billing\IssueInvoice — a legacy `type = InvoiceType::Quote`
 * row (App\Livewire\TallStackInvoiceForm reuses this same edit page for
 * both) must never reach this path, even if a status value it happens to
 * carry would otherwise satisfy canTransitionTo(Amended).
 */
class AmendIssuedInvoice
{
    public function __construct(
        private DocumentNumberGenerator $numberGenerator,
        private IssueInvoice $issueInvoice,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<int, array{title: string, description?: ?string, quantity: float|string, unit_cost: float|string, discount?: float|string, discount_is_percentage?: bool, product_id?: ?int, tax_category?: mixed}>  $newItems
     */
    public function amend(Invoice $original, string $reason, array $newItems, User $actor): Invoice
    {
        if (! $actor->hasCompanyRole($original->company, ...CompanyRole::documentAmendmentRoles())) {
            throw new RuntimeException('Only Admin or Owner may amend an issued invoice.');
        }

        if ($original->type !== InvoiceType::Invoice) {
            throw new RuntimeException('Only a plain invoice can be amended this way — a legacy quote is never issued in the tax-snapshot sense, so it has nothing to amend.');
        }

        if (! $original->status->canTransitionTo(InvoiceStatus::Amended)) {
            throw new RuntimeException(
                "Invoice cannot be amended from status [{$original->status->value}]."
            );
        }

        if ($original->correction()->exists()) {
            throw new RuntimeException('This invoice has already been corrected.');
        }

        return DB::transaction(function () use ($original, $reason, $newItems, $actor) {
            $newInvoice = Invoice::create([
                'company_id' => $original->company_id,
                'client_id' => $original->client_id,
                'sales_order_id' => $original->sales_order_id,
                'type' => InvoiceType::Invoice,
                'pricing_mode' => $original->pricing_mode ?? PricingMode::Exclusive,
                'currency_code' => $original->currency_code,
                'invoice_date' => now()->toDateString(),
                'due_date' => $original->due_date,
                'discount' => $original->discount,
                'discount_is_percentage' => $original->discount_is_percentage,
                'terms' => $original->terms,
                'public_notes' => $original->public_notes,
                'status' => InvoiceStatus::Draft,
                'original_invoice_id' => $original->id,
                'correction_reason' => $reason,
            ]);

            $this->createItems($newInvoice, $newItems);

            $number = $this->numberGenerator->next($original->company, 'invoice_amendment');
            $newInvoice->forceFill(['number' => $number])->save();

            $newInvoice = $this->issueInvoice->issue($newInvoice->fresh(['items']), $actor);

            $original->forceFill(['status' => InvoiceStatus::Amended])->save();

            $this->auditLogger->record(
                $original->company,
                'invoice.amended',
                $original,
                [],
                ['new_invoice_id' => $newInvoice->id, 'new_invoice_number' => $newInvoice->number],
                $reason,
            );

            return $newInvoice;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function createItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $discountIsPercentage = (bool) ($item['discount_is_percentage'] ?? false);

            $lineGross = $quantity * $unitCost;
            $lineDiscount = $discountIsPercentage ? $lineGross * ($discount / 100) : $discount;
            $lineTotal = round(max(0, $lineGross - $lineDiscount), 2);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'] ?? null,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'discount' => $discount,
                'discount_is_percentage' => $discountIsPercentage,
                'sort_order' => $index,
                'line_total' => $lineTotal,
                'tax_category' => $item['tax_category'] ?? null,
            ]);
        }
    }
}
