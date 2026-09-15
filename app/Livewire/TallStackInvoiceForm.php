<?php

namespace App\Livewire;

use App\Actions\Billing\AmendIssuedInvoice;
use App\Actions\Billing\FileOrAdjustTaxRecap;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\VoidAndReissueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Filament\Support\Money;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\BillingMailer;
use App\Services\DocumentNumberGenerator;
use App\Services\InvoiceTotalsCalculator;
use App\Support\TallStack\StatusColor;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack detail/edit page for an Invoice — the sibling page to
 * App\Livewire\TallStackInvoices. Mirrors
 * App\Filament\Resources\Invoices\Schemas\InvoiceForm (header fields) and
 * ...\RelationManagers\ItemsRelationManager (line items, including the
 * `tax_rate_ids` virtual field synced via
 * App\Services\InvoiceTotalsCalculator::syncItemTaxes()) field-for-field.
 * Every status transition/correction goes through the exact same
 * App\Actions\Billing\* classes
 * ...\Tables\InvoicesTable's row actions call — `status` is never a form
 * field here, and totals/balance are never hand-set.
 *
 * Scoped to plain, non-recurring `type = InvoiceType::Invoice` rows only
 * — deliberately drops InvoiceForm's "Recurring" section, since the
 * Invoices register this page lives under (App\Livewire\TallStackInvoices)
 * already excludes `is_recurring` rows (those stay on the separate,
 * untouched App\Filament\Resources\RecurringInvoices resource/nav entry
 * for this phase).
 *
 * A brand-new invoice must exist before it can carry line items (same
 * reason as TallStackQuotationForm) — saving a brand-new invoice redirects
 * into its own edit route before the items table becomes available.
 */
#[Layout('components.tallstack.app')]
class TallStackInvoiceForm extends Component
{
    use Interactions;

    public Company $company;

    public ?Invoice $invoice = null;

    // Header fields — InvoiceForm's own field set, minus `type`/`status`
    // (never form fields — see class docblock) and the Recurring section
    // (out of scope for this register, see class docblock).
    public ?string $client_id = null;

    public ?string $number = null;

    public string $pricing_mode = PricingMode::Exclusive->value;

    public ?string $po_number = null;

    public ?string $invoice_date = null;

    public ?string $due_date = null;

    public ?string $currency_code = null;

    public float $discount = 0;

    public bool $discount_is_percentage = false;

    public ?string $terms = null;

    public ?string $public_notes = null;

    public ?string $private_notes = null;

    public ?string $footer = null;

    // Line item modal state — mirrors ItemsRelationManager's own form.
    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public ?string $item_product_id = null;

    public ?string $item_title = null;

    public ?string $item_description = null;

    public float $item_quantity = 1;

    public float $item_unit_cost = 0;

    public float $item_discount = 0;

    public bool $item_discount_is_percentage = false;

    /** @var array<int, string> */
    public array $item_tax_rate_ids = [];

    // Send modal state.
    public bool $showSendModal = false;

    public string $sendCc = '';

    // Correction (Amend / Void & reissue) modal state — both actions take
    // the same shape (reason + corrected line items), matching
    // InvoicesTable::correctionSchema()/mapItems() exactly.
    public bool $showCorrectionModal = false;

    /** @var 'amend'|'void'|null */
    public ?string $correctionAction = null;

    public string $correctionReason = '';

    /** @var array<int, array<string, mixed>> */
    public array $correctionItems = [];

    // Tax recap file/adjust modal state — mirrors InvoiceInfolist's
    // fileOrAdjustTaxRecapAction() schema exactly.
    public bool $showTaxRecapModal = false;

    public ?string $taxRecapExternalReference = null;

    public string $taxRecapManualEntryStatus = 'pending';

    public ?string $taxRecapFilingDate = null;

    public ?string $taxRecapAttachmentReference = null;

    public ?string $taxRecapNotes = null;

    public ?string $taxRecapAdjustmentReason = null;

    public function mount(Company $company, ?Invoice $invoice = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        // Route binding for `invoice` happens before this mount() body
        // runs and before Filament::setTenant() above activates
        // BelongsToCompany's scope — same explicit re-check as
        // TallStackQuotationForm::mount() and the PDF controllers.
        if ($invoice) {
            abort_unless($invoice->company_id === $company->id, 404);
            abort_unless($invoice->type === InvoiceType::Invoice, 404);

            $this->invoice = $invoice->loadMissing(['items.product', 'items.taxes', 'client', 'taxRecap', 'originalInvoice', 'correction', 'salesOrder']);
            $this->client_id = (string) $invoice->client_id;
            $this->number = $invoice->number;
            $this->pricing_mode = $invoice->pricing_mode?->value ?? PricingMode::Exclusive->value;
            $this->po_number = $invoice->po_number;
            $this->invoice_date = $invoice->invoice_date?->toDateString();
            $this->due_date = $invoice->due_date?->toDateString();
            $this->currency_code = $invoice->currency_code;
            $this->discount = (float) $invoice->discount;
            $this->discount_is_percentage = $invoice->discount_is_percentage;
            $this->terms = $invoice->terms;
            $this->public_notes = $invoice->public_notes;
            $this->private_notes = $invoice->private_notes;
            $this->footer = $invoice->footer;

            return;
        }

        $this->invoice_date = now()->toDateString();
        $this->currency_code = $company->currency_code;
    }

    /** Same client-default-discount prefill as InvoiceForm's own afterStateUpdated(). */
    public function updatedClientId(?string $value): void
    {
        if (! $value || $this->invoice) {
            return;
        }

        if ($client = Client::query()->where('company_id', $this->company->id)->find($value)) {
            $this->discount = (float) $client->default_discount;
            $this->discount_is_percentage = (bool) $client->default_discount_is_percentage;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $this->company->id)],
            'number' => ['nullable', 'string', 'max:255'],
            'pricing_mode' => ['required'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'discount' => ['numeric', 'min:0'],
            'discount_is_percentage' => ['boolean'],
            'terms' => ['nullable', 'string'],
            'public_notes' => ['nullable', 'string'],
            'private_notes' => ['nullable', 'string'],
            'footer' => ['nullable', 'string'],
        ]);

        $payload = [
            'client_id' => $data['client_id'],
            'number' => filled($data['number']) ? $data['number'] : null,
            'pricing_mode' => $this->pricing_mode,
            'po_number' => $this->po_number,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'currency_code' => $this->currency_code,
            'discount' => $this->discount,
            'discount_is_percentage' => $this->discount_is_percentage,
            'terms' => $this->terms,
            'public_notes' => $this->public_notes,
            'private_notes' => $this->private_notes,
            'footer' => $this->footer,
        ];

        if ($this->invoice) {
            $this->authorize('update', $this->invoice);

            $this->invoice->update($payload);
            app(InvoiceTotalsCalculator::class)->recalculate($this->invoice);

            $this->toast()->success('Invoice saved.')->send();

            return;
        }

        $this->authorize('create', Invoice::class);

        if (blank($payload['number'])) {
            $payload['number'] = app(DocumentNumberGenerator::class)->next($this->company, 'invoice');
        }

        $payload['company_id'] = $this->company->id;
        $payload['type'] = InvoiceType::Invoice;
        $payload['status'] = InvoiceStatus::Draft;
        $payload['is_recurring'] = false;

        $invoice = Invoice::create($payload);

        $this->toast()->success('Invoice created.', 'Add line items below, then continue the workflow.')->send();

        $this->redirect(route('tallstack.invoices.edit', [$this->company, $invoice]), navigate: false);
    }

    // --- Line items -----------------------------------------------------

    public function addItem(): void
    {
        $this->resetItemForm();
        $this->showItemModal = true;
    }

    public function editItem(int $id): void
    {
        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $this->editingItemId = $item->id;
        $this->item_product_id = $item->product_id ? (string) $item->product_id : null;
        $this->item_title = $item->title;
        $this->item_description = $item->description;
        $this->item_quantity = (float) $item->quantity;
        $this->item_unit_cost = (float) $item->unit_cost;
        $this->item_discount = (float) $item->discount;
        $this->item_discount_is_percentage = (bool) $item->discount_is_percentage;
        $this->item_tax_rate_ids = $item->taxes->pluck('tax_rate_id')->filter()->map(fn ($id) => (string) $id)->values()->all();
        $this->showItemModal = true;
    }

    /** Same product -> title/unit_cost/default-tax autofill as ItemsRelationManager's afterStateUpdated(). */
    public function updatedItemProductId(?string $value): void
    {
        if (! $value) {
            return;
        }

        if ($product = Product::query()->where('company_id', $this->company->id)->find($value)) {
            $this->item_title = $product->name;
            $this->item_unit_cost = (float) $product->unit_cost;
            $this->item_tax_rate_ids = $product->default_tax_rate_id ? [(string) $product->default_tax_rate_id] : [];
        }
    }

    public function saveItem(): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $data = $this->validate([
            'item_title' => ['required', 'string', 'max:255'],
            'item_description' => ['nullable', 'string'],
            'item_quantity' => ['required', 'numeric', 'min:0.0001'],
            'item_unit_cost' => ['required', 'numeric', 'min:0'],
            'item_discount' => ['numeric', 'min:0'],
            'item_discount_is_percentage' => ['boolean'],
        ]);

        $productId = $this->item_product_id
            ? Product::query()->where('company_id', $this->company->id)->whereKey($this->item_product_id)->value('id')
            : null;

        $payload = [
            'product_id' => $productId,
            'title' => $data['item_title'],
            'description' => $data['item_description'],
            'quantity' => $data['item_quantity'],
            'unit_cost' => $data['item_unit_cost'],
            'discount' => $data['item_discount'],
            'discount_is_percentage' => $data['item_discount_is_percentage'],
        ];

        $taxRateIds = array_map('intval', $this->item_tax_rate_ids);

        if ($this->editingItemId) {
            $item = $this->scopedItem($this->editingItemId);

            if ($item) {
                $item->update($payload);
                app(InvoiceTotalsCalculator::class)->syncItemTaxes($item, $taxRateIds);
            }
        } else {
            $payload['invoice_id'] = $this->invoice->id;
            $payload['sort_order'] = ((int) $this->invoice->items()->max('sort_order')) + 1;
            $item = InvoiceItem::create($payload);
            app(InvoiceTotalsCalculator::class)->syncItemTaxes($item, $taxRateIds);
        }

        app(InvoiceTotalsCalculator::class)->recalculate($this->invoice);
        $this->invoice->refresh()->load(['items.product', 'items.taxes']);

        $this->showItemModal = false;
        $this->resetItemForm();
        $this->toast()->success('Line item saved.')->send();
    }

    public function deleteItem(int $id): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $item->delete();
        app(InvoiceTotalsCalculator::class)->recalculate($this->invoice);
        $this->invoice->refresh()->load(['items.product', 'items.taxes']);

        $this->toast()->success('Line item removed.')->send();
    }

    private function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->item_product_id = null;
        $this->item_title = null;
        $this->item_description = null;
        $this->item_quantity = 1;
        $this->item_unit_cost = 0;
        $this->item_discount = 0;
        $this->item_discount_is_percentage = false;
        $this->item_tax_rate_ids = [];
    }

    private function scopedItem(int $id): ?InvoiceItem
    {
        if (! $this->invoice) {
            return null;
        }

        return $this->invoice->items->firstWhere('id', $id);
    }

    // --- Issue / Send -----------------------------------------------------

    public function issue(): void
    {
        if (! $this->invoice) {
            return;
        }

        try {
            app(IssueInvoice::class)->issue($this->invoice, auth()->user());
            $this->invoice->refresh()->load(['items.product', 'items.taxes', 'taxRecap']);
            $this->toast()->success('Invoice issued.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not issue invoice', $e->getMessage())->send();
        }
    }

    public function openSendModal(): void
    {
        $this->sendCc = '';
        $this->showSendModal = true;
    }

    /**
     * Same reasoning as TallStackQuotationForm::send() — App\Services\
     * BillingMailer::sendInvoice() is called unmodified; this page never
     * builds its own email path.
     */
    public function send(): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $cc = collect(preg_split('/[,\n]/', $this->sendCc) ?: [])
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();

        try {
            app(BillingMailer::class)->sendInvoice($this->invoice, cc: $cc);
            $this->showSendModal = false;
            $this->toast()->success('Invoice sent.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not send invoice', $e->getMessage())->send();
        }
    }

    // --- Amend / Void & reissue ------------------------------------------

    public function openCorrectionModal(string $type): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->correctionAction = $type;
        $this->correctionReason = '';
        $this->correctionItems = $this->invoice->items->sortBy('sort_order')->values()->map(fn (InvoiceItem $item) => [
            'product_id' => $item->product_id ? (string) $item->product_id : null,
            'title' => $item->title,
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_cost' => (float) $item->unit_cost,
            'discount' => (float) $item->discount,
            'discount_is_percentage' => (bool) $item->discount_is_percentage,
            'tax_category' => $item->tax_category?->value,
        ])->all();
        $this->showCorrectionModal = true;
    }

    public function addCorrectionItem(): void
    {
        $this->correctionItems[] = [
            'product_id' => null,
            'title' => '',
            'description' => null,
            'quantity' => 1,
            'unit_cost' => 0,
            'discount' => 0,
            'discount_is_percentage' => false,
            'tax_category' => null,
        ];
    }

    public function removeCorrectionItem(int $index): void
    {
        unset($this->correctionItems[$index]);
        $this->correctionItems = array_values($this->correctionItems);
    }

    /** Applies the pending Amend or Void & reissue, per correctionAction — never both, and never a bare status edit; identical arguments to InvoicesTable::mapItems(). */
    public function submitCorrection(): void
    {
        if (! $this->invoice || ! $this->correctionAction) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $this->validate([
            'correctionReason' => ['required', 'string'],
            'correctionItems' => ['required', 'array', 'min:1'],
            'correctionItems.*.title' => ['required', 'string', 'max:255'],
            'correctionItems.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'correctionItems.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $items = array_map(fn (array $item) => [
            'product_id' => filled($item['product_id'] ?? null) ? $item['product_id'] : null,
            'title' => $item['title'],
            'description' => $item['description'] ?? null,
            'quantity' => $item['quantity'],
            'unit_cost' => $item['unit_cost'],
            'discount' => $item['discount'] ?? 0,
            'discount_is_percentage' => $item['discount_is_percentage'] ?? false,
            'tax_category' => filled($item['tax_category'] ?? null) ? TaxCategory::from($item['tax_category']) : null,
        ], $this->correctionItems);

        try {
            $new = $this->correctionAction === 'amend'
                ? app(AmendIssuedInvoice::class)->amend($this->invoice, $this->correctionReason, $items, auth()->user())
                : app(VoidAndReissueInvoice::class)->voidAndReissue($this->invoice, $this->correctionReason, $items, auth()->user());

            $this->showCorrectionModal = false;
            $this->toast()->success(
                $this->correctionAction === 'amend' ? 'Invoice amended.' : 'Invoice voided and reissued.',
                "Created #{$new->number}."
            )->send();

            $this->redirect(route('tallstack.invoices.edit', [$this->company, $new]), navigate: false);
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not apply correction', $e->getMessage())->send();
        }
    }

    // --- Tax recap (e-Faktur) ---------------------------------------------

    public function openTaxRecapModal(): void
    {
        if (! $this->invoice?->taxRecap) {
            return;
        }

        $recap = $this->invoice->taxRecap;
        $this->taxRecapExternalReference = $recap->external_reference;
        $this->taxRecapManualEntryStatus = $recap->manual_entry_status ?? 'pending';
        $this->taxRecapFilingDate = $recap->filing_date?->toDateString();
        $this->taxRecapAttachmentReference = $recap->attachment_reference;
        $this->taxRecapNotes = $recap->notes;
        $this->taxRecapAdjustmentReason = null;
        $this->showTaxRecapModal = true;
    }

    public function submitTaxRecap(): void
    {
        if (! $this->invoice?->taxRecap) {
            return;
        }

        $this->validate([
            'taxRecapManualEntryStatus' => ['required', 'in:pending,filed'],
            'taxRecapExternalReference' => ['nullable', 'string', 'max:255'],
            'taxRecapFilingDate' => ['nullable', 'date'],
            'taxRecapAttachmentReference' => ['nullable', 'string', 'max:255'],
            'taxRecapNotes' => ['nullable', 'string'],
        ]);

        try {
            app(FileOrAdjustTaxRecap::class)->file(
                $this->invoice->taxRecap,
                [
                    'external_reference' => $this->taxRecapExternalReference,
                    'manual_entry_status' => $this->taxRecapManualEntryStatus,
                    'filing_date' => $this->taxRecapFilingDate,
                    'attachment_reference' => $this->taxRecapAttachmentReference,
                    'notes' => $this->taxRecapNotes,
                ],
                auth()->user(),
                filled($this->taxRecapAdjustmentReason) ? $this->taxRecapAdjustmentReason : null,
            );

            $this->invoice->refresh()->load('taxRecap');
            $this->showTaxRecapModal = false;
            $this->toast()->success('Tax recap updated.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not update tax recap', $e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $currency = $this->invoice?->currency_code ?: $this->company->currency_code;

        $items = $this->invoice
            ? $this->invoice->items->sortBy('sort_order')->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'image' => $item->product?->getImageDataUri(),
                'title' => $item->title,
                'quantity' => (float) $item->quantity,
                'unit_cost' => Money::format((float) $item->unit_cost, $currency),
                'taxes' => $item->taxes->pluck('name')->filter()->values()->all(),
                'line_total' => Money::format((float) $item->line_total, $currency),
            ])->values()
            : collect();

        $taxRecapAlreadyFiled = $this->invoice?->taxRecap
            && (filled($this->invoice->taxRecap->filing_date) || $this->invoice->taxRecap->manual_entry_status === 'filed');

        return view('livewire.tallstack-invoice-form', [
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'taxRates' => $this->company->taxRates()->orderBy('name')->get(['id', 'name']),
            'pricingModes' => PricingMode::cases(),
            'items' => $items,
            'currency' => $currency,
            'statusColor' => $this->invoice ? StatusColor::map($this->invoice->status->getColor()) : null,
            'subtotal' => $this->invoice ? Money::format((float) $this->invoice->subtotal, $currency) : Money::format(0, $currency),
            'taxTotal' => $this->invoice ? Money::format((float) $this->invoice->tax_total, $currency) : Money::format(0, $currency),
            'total' => $this->invoice ? Money::format((float) $this->invoice->total, $currency) : Money::format(0, $currency),
            'amountPaid' => $this->invoice ? Money::format((float) $this->invoice->amount_paid, $currency) : Money::format(0, $currency),
            'balance' => $this->invoice ? Money::format((float) $this->invoice->balance, $currency) : Money::format(0, $currency),
            'taxRecapAlreadyFiled' => $taxRecapAlreadyFiled,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'invoices',
            'title' => $this->invoice ? $this->invoice->number : 'New invoice',
        ]);
    }
}
