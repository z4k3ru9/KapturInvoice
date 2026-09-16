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
use App\Enums\UnitOfMeasure;
use App\Livewire\Concerns\AutosavesDraft;
use App\Livewire\Concerns\ManagesDocuments;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\BillingMailer;
use App\Services\DocumentNumberGenerator;
use App\Services\InvoiceTotalsCalculator;
use App\Support\Dashboard\Money;
use App\Support\Html\RichTextSanitizer;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack detail/edit page for an Invoice — the sibling page to
 * App\Livewire\TallStackInvoices. Mirrors the equivalent pre-TallStackUI
 * Filament invoice form (header fields) and its items relation manager
 * (line items, including the `tax_rate_ids` virtual field synced via
 * App\Services\InvoiceTotalsCalculator::syncItemTaxes()) field-for-field.
 * Every status transition/correction goes through the exact same
 * App\Actions\Billing\* classes that resource's own table row actions
 * called — `status` is never a form field here, and totals/balance are
 * never hand-set.
 *
 * Scoped to plain, non-recurring `type = InvoiceType::Invoice` rows only
 * — deliberately drops the equivalent Filament form's "Recurring" section,
 * since the Invoices register this page lives under
 * (App\Livewire\TallStackInvoices) already excludes `is_recurring` rows
 * (those get their own dedicated page, App\Livewire\TallStackRecurringInvoices).
 *
 * A brand-new invoice must exist before it can carry line items (same
 * reason as TallStackQuotationForm) — saving a brand-new invoice redirects
 * into its own edit route before the items table becomes available.
 *
 * **Also the edit page for a legacy `type = InvoiceType::Quote` row**
 * (App\Livewire\TallStackQuotes, routed at `tallstack.quotes.edit`, same
 * URL segment as `tallstack.invoices.edit` — see routes/web.php). This is
 * a deliberate reuse, not a duplicate: the old Filament `QuoteResource`
 * called `InvoiceForm::configure($schema)` directly with the exact same
 * reasoning ("a quote is structurally identical to an invoice until it's
 * converted") — see that resource's own docblock, retrievable from git
 * history at the Filament-removal commit. There is never a "new quote"
 * flow through this component (`mount()`'s `$invoice === null` branch
 * always creates a real Invoice) — legacy quotes are import-only rows,
 * per App\Livewire\TallStackQuotes's own docblock.
 *
 * `getIsQuoteProperty()` (`$this->isQuote` in the view) is the one
 * branch point the header/action-bar markup and `send()` use to tell the
 * two apart: a quote never shows Issue/Amend/Void/tax-recap (those stay
 * gated to `InvoiceType::Invoice` inside their own Action classes too —
 * App\Actions\Billing\IssueInvoice/AmendIssuedInvoice/
 * VoidAndReissueInvoice — never only by hiding the button here), and
 * `send()` dispatches through App\Services\BillingMailer::sendQuote()
 * instead of `sendInvoice()`. Every other field, the line-item editor,
 * document autosave (App\Livewire\Concerns\AutosavesDraft — the
 * whitelisted fields are the same free-text header columns on both
 * types), and document uploads (App\Livewire\Concerns\ManagesDocuments)
 * work identically for either type, since both are the same `Invoice`
 * row.
 */
#[Layout('components.tallstack.app')]
class TallStackInvoiceForm extends Component
{
    use AutosavesDraft, Interactions, ManagesDocuments, WithFileUploads;

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

    /**
     * The Job this invoice is billed against (App\Models\Invoice::
     * sales_order_id, a real #[Fillable] column that this form previously
     * never referenced at all — see App\Models\SalesOrder::invoices()).
     * Prefilled from a `?sales_order_id=` query param when this page is
     * reached via the Job's own Billing tab "Create invoice" action
     * (tallstack-sales-order.blade.php), and otherwise pickable directly
     * here — scoped to the selected client's own open jobs, see
     * `render()`'s `salesOrders` option list — since Invoices > Create
     * already exists as its own entry point and shouldn't require going
     * through the Job page first.
     */
    public ?string $sales_order_id = null;

    public float $discount = 0;

    public bool $discount_is_percentage = false;

    public ?string $terms = null;

    public ?string $public_notes = null;

    public ?string $private_notes = null;

    public ?string $footer = null;

    // Inline line-item add/edit form state (Phase 13 repair plan — replaces
    // the previous <x-modal wire="showItemModal"> round-trip; the
    // underlying field set/validation/save shape is unchanged, only the
    // container). `itemFormOpen` gates whether the inline form row renders
    // at all; `editingItemId` distinguishes "editing this row" (set) from
    // "adding a new trailing row" (null) — mirrors ItemsRelationManager's
    // own form fields.
    public bool $itemFormOpen = false;

    public ?int $editingItemId = null;

    public ?string $item_product_id = null;

    public ?string $item_title = null;

    public ?string $item_description = null;

    public float $item_quantity = 1;

    public ?string $item_unit = null;

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

    // Document attachment state — see App\Livewire\Concerns\ManagesDocuments.
    /** @var TemporaryUploadedFile|null */
    public $newDocument = null;

    public function mount(Company $company, ?Invoice $invoice = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding for `invoice` happens before this mount() body
        // runs and before app(Tenancy::class)->set() above activates
        // BelongsToCompany's scope — same explicit re-check as
        // TallStackQuotationForm::mount() and the PDF controllers.
        if ($invoice) {
            abort_unless($invoice->company_id === $company->id, 404);
            abort_unless(in_array($invoice->type, [InvoiceType::Invoice, InvoiceType::Quote], true), 404);

            $this->invoice = $invoice->loadMissing(['items.product', 'items.taxes', 'client', 'taxRecap', 'originalInvoice', 'correction', 'salesOrder.quotation']);
            $this->client_id = (string) $invoice->client_id;
            $this->number = $invoice->number;
            $this->pricing_mode = $invoice->pricing_mode?->value ?? PricingMode::Exclusive->value;
            $this->po_number = $invoice->po_number;
            $this->invoice_date = $invoice->invoice_date?->toDateString();
            $this->due_date = $invoice->due_date?->toDateString();
            $this->currency_code = $invoice->currency_code;
            $this->sales_order_id = $invoice->sales_order_id ? (string) $invoice->sales_order_id : null;
            $this->discount = (float) $invoice->discount;
            $this->discount_is_percentage = $invoice->discount_is_percentage;
            $this->terms = $invoice->terms;
            $this->public_notes = $invoice->public_notes;
            $this->private_notes = $invoice->private_notes;
            $this->footer = $invoice->footer;

            $this->initializeAutosaveVersion();

            return;
        }

        $this->invoice_date = now()->toDateString();
        $this->currency_code = $company->currency_code;
        // Phase 11 (repair plan, decision gate G5): prefill Terms from the
        // company-wide default, never overwriting a real saved value —
        // this whole branch only runs when there is no existing $invoice.
        $this->terms = $company->default_payment_terms;

        // Reached from the Job's own Billing tab "Create invoice" action
        // (tallstack-sales-order.blade.php) — same `?<field>=` query-param
        // prefill convention TallStackStatementOfAccount::mount() already
        // uses for period_start/period_end. Silently ignored if the id
        // doesn't resolve to a real job on this tenant (e.g. a stale
        // link) rather than aborting the whole page.
        $salesOrderId = request()->query('sales_order_id');

        if ($salesOrderId && $job = SalesOrder::query()->where('company_id', $company->id)->find($salesOrderId)) {
            $this->sales_order_id = (string) $job->id;
            $this->client_id = (string) $job->client_id;
        }
    }

    /** Livewire computed property (`$this->isQuote` in the view) — see class docblock's "Also the edit page for a legacy type=Quote row" section. */
    public function getIsQuoteProperty(): bool
    {
        return $this->invoice?->type === InvoiceType::Quote;
    }

    /** Same client-default-discount prefill as InvoiceForm's own afterStateUpdated(). */
    public function updatedClientId(?string $value): void
    {
        // A previously-picked job stops being valid once it no longer
        // belongs to the (now different) selected client — the
        // `salesOrders` option list in render() is scoped per-client, so
        // a stale selection would otherwise silently point at a job for
        // someone else's invoice.
        if ($this->sales_order_id) {
            $job = SalesOrder::query()->where('company_id', $this->company->id)->find($this->sales_order_id);

            if (! $job || (string) $job->client_id !== (string) $value) {
                $this->sales_order_id = null;
            }
        }

        if (! $value || $this->invoice) {
            return;
        }

        if ($client = Client::query()->where('company_id', $this->company->id)->find($value)) {
            $this->discount = (float) $client->default_discount;
            $this->discount_is_percentage = (bool) $client->default_discount_is_percentage;
        }
    }

    // --- Draft autosave ---------------------------------------------------
    //
    // Wired only onto the plain free-text/metadata header fields — po_number,
    // terms, public_notes, private_notes, footer — never client_id, number,
    // pricing_mode, dates, or the discount fields, since those either
    // already go through save()'s own validation/redirect path or feed
    // App\Services\InvoiceTotalsCalculator's recalculation, which this raw
    // conditional-UPDATE autosave deliberately never triggers. See
    // App\Livewire\Concerns\AutosavesDraft's docblock for the full
    // algorithm this ports from the old Filament reference implementation.

    public function updatedPoNumber(): void
    {
        $this->autosaveDraft();
    }

    public function updatedTerms(): void
    {
        $this->terms = app(RichTextSanitizer::class)->sanitize($this->terms);
        $this->autosaveDraft();
    }

    public function updatedPublicNotes(): void
    {
        $this->public_notes = app(RichTextSanitizer::class)->sanitize($this->public_notes);
        $this->autosaveDraft();
    }

    public function updatedPrivateNotes(): void
    {
        $this->private_notes = app(RichTextSanitizer::class)->sanitize($this->private_notes);
        $this->autosaveDraft();
    }

    public function updatedFooter(): void
    {
        $this->footer = app(RichTextSanitizer::class)->sanitize($this->footer);
        $this->autosaveDraft();
    }

    protected function autosaveModel(): ?Model
    {
        return $this->invoice;
    }

    /** @return list<string> */
    protected function autosaveFields(): array
    {
        return ['po_number', 'terms', 'public_notes', 'private_notes', 'footer'];
    }

    /**
     * Same "Auditor is read-only everywhere" boundary save() enforces via
     * its own explicit $this->authorize('update', ...) call
     * (App\Providers\AppServiceProvider::registerCompanyRoleGate()) —
     * autosave must not become a side channel that bypasses it just
     * because it never routes through save()'s validated form submission.
     */
    protected function autosaveGuard(): bool
    {
        return $this->invoice !== null
            && $this->invoice->status === InvoiceStatus::Draft
            && Gate::allows('update', $this->invoice);
    }

    /**
     * Header fields (client_id/pricing_mode/sales_order_id/etc.) are only
     * ever mutable while App\Enums\InvoiceStatus::isLocked() is false —
     * `Draft`/`Approved`. Once an invoice is Issued (or carries any other
     * locked status, including the legacy read-only-history ones — see
     * that method's own docblock) this form's own header/totals/tax
     * snapshot must stay immutable per CLAUDE.md's Phase 04 rules; the
     * only legal corrections from here on are the Amend/Void & reissue
     * buttons this same page already renders
     * (App\Actions\Billing\AmendIssuedInvoice/VoidAndReissueInvoice via
     * openCorrectionModal()). Checked against the loaded model's own
     * status — never a mutable Livewire property, since `status` is
     * deliberately never bound as one on this form (see class docblock).
     */
    public function save(): void
    {
        if ($this->invoice && $this->invoice->status->isLocked()) {
            $this->toast()->error(
                'Could not save invoice',
                'This invoice has already been issued and its header can no longer be edited directly. Use Amend or Void & reissue instead.'
            )->send();

            return;
        }

        $sanitizer = app(RichTextSanitizer::class);
        $this->terms = $sanitizer->sanitize($this->terms);
        $this->public_notes = $sanitizer->sanitize($this->public_notes);
        $this->private_notes = $sanitizer->sanitize($this->private_notes);
        $this->footer = $sanitizer->sanitize($this->footer);

        $data = $this->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $this->company->id)],
            'number' => ['nullable', 'string', 'max:255'],
            'pricing_mode' => ['required'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'sales_order_id' => ['nullable', Rule::exists('sales_orders', 'id')->where('company_id', $this->company->id)],
            'discount' => ['numeric', 'min:0'],
            'discount_is_percentage' => ['boolean'],
            'terms' => ['nullable', 'string'],
            'public_notes' => ['nullable', 'string'],
            'private_notes' => ['nullable', 'string'],
            'footer' => ['nullable', 'string'],
        ]);

        // A cross-field check Rule::exists alone can't express — the job
        // must belong to the same client actually being invoiced, not
        // merely the same company (an editable manual picker on this form
        // means the client can change after a job was picked, or vice
        // versa — see updatedClientId()'s own reset for the common case;
        // this is the authoritative backstop for it).
        if (filled($data['sales_order_id'] ?? null)) {
            $job = SalesOrder::query()->where('company_id', $this->company->id)->find($data['sales_order_id']);

            if (! $job || (string) $job->client_id !== (string) $data['client_id']) {
                $this->addError('sales_order_id', 'The selected job does not belong to this client.');

                return;
            }
        }

        $payload = [
            'client_id' => $data['client_id'],
            'number' => filled($data['number']) ? $data['number'] : null,
            'pricing_mode' => $this->pricing_mode,
            'po_number' => $this->po_number,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'currency_code' => $this->currency_code,
            'sales_order_id' => filled($data['sales_order_id'] ?? null) ? $data['sales_order_id'] : null,
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
        $this->itemFormOpen = true;
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
        $this->item_unit = $item->unit?->value;
        $this->item_unit_cost = (float) $item->unit_cost;
        $this->item_discount = (float) $item->discount;
        $this->item_discount_is_percentage = (bool) $item->discount_is_percentage;
        $this->item_tax_rate_ids = $item->taxes->pluck('tax_rate_id')->filter()->map(fn ($id) => (string) $id)->values()->all();
        $this->itemFormOpen = true;
    }

    /** Closes the inline add/edit form without saving — mirrors the old modal's "Cancel". */
    public function cancelItemForm(): void
    {
        $this->itemFormOpen = false;
        $this->resetItemForm();
    }

    /** Same product -> title/unit_cost/default-tax autofill as ItemsRelationManager's afterStateUpdated(). */
    public function updatedItemProductId(?string $value): void
    {
        if (! $value) {
            return;
        }

        if ($product = Product::query()->where('company_id', $this->company->id)->find($value)) {
            $this->item_title = $product->name;
            $this->item_unit = $product->unit?->value;
            $this->item_unit_cost = (float) $product->unit_cost;
            $this->item_tax_rate_ids = $product->default_tax_rate_id ? [(string) $product->default_tax_rate_id] : [];
        }
    }

    public function saveItem(): void
    {
        // Matches App\Livewire\TallStackVendorBillForm/
        // TallStackVendorPurchaseOrderForm's own established guard — this
        // form was missing it (confirmed via a direct comparison while
        // building inline quick-edit for quantity/unit cost below): an
        // issued/sent/paid invoice's line items must never be mutable,
        // matching CLAUDE.md's own "issued documents are never physically
        // deleted" guard (which implies never silently re-totaled either).
        if (! $this->invoice || $this->invoice->status !== InvoiceStatus::Draft) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $data = $this->validate([
            'item_title' => ['required', 'string', 'max:255'],
            'item_description' => ['nullable', 'string'],
            'item_quantity' => ['required', 'integer', 'min:1'],
            'item_unit' => ['nullable', Rule::enum(UnitOfMeasure::class)],
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
            'unit' => $data['item_unit'],
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

        $this->itemFormOpen = false;
        $this->resetItemForm();
        $this->toast()->success('Line item saved.')->send();
    }

    /**
     * Inline quick-edit for a single row's quantity or unit cost, straight
     * from the table cell — no expand-to-a-full-form round trip for the
     * two fields people adjust most often. Deliberately narrow: only
     * `quantity`/`unit_cost` are reachable this way (an explicit
     * whitelist, not a free-form field name from the client) — changing
     * the product, title, description, discount, or taxes still goes
     * through the full editItem()/saveItem() form, since those need more
     * context (e.g. a product change re-triggers the title/tax autofill)
     * than a single-cell edit should carry. Same guards as saveItem(): a
     * non-draft invoice's items are immutable, and the same authorization
     * gate applies. Reuses InvoiceTotalsCalculator::recalculate() — the
     * one place line_total/subtotal/tax_total/total/balance are ever
     * computed — rather than recomputing anything itself.
     */
    public function updateItemInline(int $id, string $field, string $value): void
    {
        if (! $this->invoice || $this->invoice->status !== InvoiceStatus::Draft) {
            return;
        }

        if (! in_array($field, ['quantity', 'unit_cost'], true)) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $validated = validator(
            [$field => $value],
            [$field => $field === 'quantity' ? ['required', 'integer', 'min:1'] : ['required', 'numeric', 'min:0']]
        )->validate();

        $item->update($validated);

        app(InvoiceTotalsCalculator::class)->recalculate($this->invoice);
        $this->invoice->refresh()->load(['items.product', 'items.taxes']);
    }

    public function deleteItem(int $id): void
    {
        if (! $this->invoice || $this->invoice->status !== InvoiceStatus::Draft) {
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
        $this->item_unit = null;
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

    /**
     * Phase 06B Slice 3 dynamic-row reorder, rebuilt after the Filament
     * removal: takes the full new ordered list of item ids (the same
     * shape Filament's own `reorderTable()` call took) and reassigns
     * `sort_order` sequentially in one Livewire round trip — never one
     * write per intermediate drag position. A no-op (never even
     * attempted) once the invoice has left Draft: reordering an issued
     * invoice's items would silently mutate an already-printed document
     * and break the positional correspondence with its frozen tax
     * snapshot's line-by-line breakdown (the same Codex review finding
     * the deleted DynamicRowReorderTest documented). This server-side
     * guard is the real authority — the Blade view only hides the drag
     * handle/keyboard controls once the invoice isn't Draft, which is UX
     * only and never trusted alone.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderItems(array $orderedIds): void
    {
        if (! $this->invoice || $this->invoice->status !== InvoiceStatus::Draft) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $orderedIds = array_map('intval', $orderedIds);

        $owned = $this->invoice->items()->whereIn('id', $orderedIds)->pluck('id')->all();

        if (count($owned) !== count($orderedIds) || array_diff($orderedIds, $owned) !== []) {
            return;
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                InvoiceItem::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        $this->invoice->refresh()->load(['items.product', 'items.taxes']);
    }

    // --- Issue / Send -----------------------------------------------------

    public function issue(): void
    {
        if (! $this->invoice) {
            return;
        }

        // Belt-and-braces alongside App\Actions\Billing\IssueInvoice's own
        // `type !== InvoiceType::Invoice` guard (the real source of
        // truth) — never reachable via the view since the Issue button
        // only renders for `! $this->isQuote`.
        if ($this->getIsQuoteProperty()) {
            $this->toast()->error('Could not issue invoice', 'A quote cannot be issued.')->send();

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
     * BillingMailer::sendInvoice()/sendQuote() is called unmodified; this
     * page never builds its own email path. Branches on `$this->isQuote`
     * since these are the same underlying `Invoice` row, just a different
     * template kind on BillingMailer's side.
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

        $isQuote = $this->getIsQuoteProperty();

        try {
            $isQuote
                ? app(BillingMailer::class)->sendQuote($this->invoice, cc: $cc)
                : app(BillingMailer::class)->sendInvoice($this->invoice, cc: $cc);

            $this->showSendModal = false;
            $this->toast()->success($isQuote ? 'Quote sent.' : 'Invoice sent.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error($isQuote ? 'Could not send quote' : 'Could not send invoice', $e->getMessage())->send();
        }
    }

    // --- Amend / Void & reissue ------------------------------------------

    public function openCorrectionModal(string $type): void
    {
        if (! $this->invoice || $this->getIsQuoteProperty()) {
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
            'correctionItems.*.quantity' => ['required', 'integer', 'min:1'],
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

    // --- Documents ---------------------------------------------------------

    public function uploadDocument(): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $this->validate($this->documentUploadRules('newDocument'));

        $this->storeUploadedDocument($this->invoice, $this->newDocument);

        $this->reset('newDocument');
        $this->toast()->success('Document uploaded.')->send();
    }

    public function deleteDocument(int $id): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->authorize('update', $this->invoice);

        if ($this->deleteScopedDocument($this->invoice, $id)) {
            $this->toast()->success('Document deleted.')->send();
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
                'unit' => $item->unit?->getAbbreviation(),
                'unit_cost' => Money::format((float) $item->unit_cost, $currency),
                // Raw, unformatted value for the inline quick-edit <input
                // type="number"> below — a browser silently renders a
                // number input blank when its value attribute isn't a
                // bare number, so the Money::format()'d string above
                // (e.g. "Rp 18.959.975") can't be reused there.
                'unit_cost_raw' => (float) $item->unit_cost,
                'taxes' => $item->taxes->pluck('name')->filter()->values()->all(),
                'line_total' => Money::format((float) $item->line_total, $currency),
            ])->values()
            : collect();

        $taxRecapAlreadyFiled = $this->invoice?->taxRecap
            && (filled($this->invoice->taxRecap->filing_date) || $this->invoice->taxRecap->manual_entry_status === 'filed');

        // Same Pending/Filed/Adjusted derivation and gray/green/blue-role
        // coloring as TallStackReports::taxRecaps() — this badge previously
        // only checked manual_entry_status === 'filed', so an adjusted
        // recap (adjusted_at set, manual_entry_status left at 'filed')
        // rendered as plain green "Filed" here while the Reports page
        // correctly showed blue "Adjusted" for the same row.
        $taxRecapStatusLabel = 'Pending';
        $taxRecapStatusColor = 'amber';
        if ($recap = $this->invoice?->taxRecap) {
            $filed = filled($recap->filing_date) || $recap->manual_entry_status === 'filed';
            $adjusted = filled($recap->adjusted_at);
            $taxRecapStatusLabel = $adjusted ? 'Adjusted' : ($filed ? 'Filed' : 'Pending');
            $taxRecapStatusColor = $adjusted ? 'blue' : ($filed ? 'green' : 'amber');
        }

        // The manual Job picker's own option list — the selected client's
        // own open (non-terminal) jobs, per App\Enums\SalesOrderStatus::
        // isTerminal(). The invoice's currently-linked job is always kept
        // even if it has since closed/been cancelled, so an existing
        // selection never silently disappears from the dropdown.
        $salesOrders = collect();
        if ($this->client_id) {
            $salesOrders = SalesOrder::query()
                ->where('company_id', $this->company->id)
                ->where('client_id', $this->client_id)
                ->get(['id', 'number', 'status'])
                ->filter(fn (SalesOrder $job) => ! $job->status->isTerminal() || (string) $job->id === (string) $this->sales_order_id)
                ->sortBy('number')
                ->values();
        }

        return view('livewire.tallstack-invoice-form', [
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'taxRates' => $this->company->taxRates()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
            'salesOrders' => $salesOrders->map(fn (SalesOrder $job) => ['label' => $job->number.' ('.$job->status->getLabel().')', 'value' => (string) $job->id])->all(),
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
            'taxRecapStatusLabel' => $taxRecapStatusLabel,
            'taxRecapStatusColor' => $taxRecapStatusColor,
            'documents' => $this->invoice ? $this->documentRows($this->invoice) : collect(),
        ])->layoutData([
            'company' => $this->company,
            'active' => $this->getIsQuoteProperty() ? 'quotes' : 'invoices',
            'title' => $this->invoice ? $this->invoice->number : 'New invoice',
        ]);
    }
}
