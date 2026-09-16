<?php

namespace App\Livewire;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PricingMode;
use App\Livewire\Concerns\AutosavesDraft;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\DocumentNumberGenerator;
use App\Services\InvoiceDuplicator;
use App\Services\InvoiceTotalsCalculator;
use App\Support\Dashboard\Money;
use App\Support\Html\RichTextSanitizer;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack detail/edit page for a Recurring Invoice template — the
 * sibling page to App\Livewire\TallStackRecurringInvoices, mirroring
 * App\Livewire\TallStackInvoiceForm's structure (same InvoiceForm header
 * fields, same ItemsRelationManager-equivalent line-item modal, same
 * InvoiceTotalsCalculator recalculation) since a recurring template
 * literally is an `App\Models\Invoice` row (`is_recurring = true`) with
 * the exact same schema as a plain invoice — see the equivalent
 * pre-TallStackUI Filament recurring invoice resource, which reused the
 * plain invoice form/infolist/items relation manager unmodified.
 *
 * Deliberately excludes every real-invoice lifecycle action
 * (Issue/Send/Amend/Void & reissue/Tax recap) — a recurring template is
 * never issued itself; only the instances "Generate now" creates are.
 * The one action this page does carry, "Generate now", is
 * App\Services\InvoiceDuplicator::generateRecurringInstance() unmodified
 * — the same action the Filament table row exposes.
 *
 * See App\Livewire\TallStackRecurringInvoices's docblock for why this
 * page (and the register) does not ship a Pause/Resume action.
 */
#[Layout('components.tallstack.app')]
class TallStackRecurringInvoiceForm extends Component
{
    use AutosavesDraft, Interactions;

    public Company $company;

    public ?Invoice $invoice = null;

    // Header fields — InvoiceForm's own field set, minus `type`/`status`
    // (never form fields on this page — a template's status has no
    // enforced meaning, see class docblock) plus the recurring-specific
    // fields InvoiceForm's own "Recurring" section exposes.
    public ?string $client_id = null;

    public ?string $number = null;

    public string $pricing_mode = PricingMode::Exclusive->value;

    public ?string $po_number = null;

    public ?string $currency_code = null;

    public float $discount = 0;

    public bool $discount_is_percentage = false;

    public ?string $terms = null;

    public ?string $public_notes = null;

    public ?string $private_notes = null;

    public ?string $footer = null;

    public string $recurring_frequency = 'monthly';

    public ?string $recurring_start_date = null;

    public ?string $recurring_end_date = null;

    public bool $no_end_date = true;

    public bool $auto_bill = false;

    // Line item modal state — mirrors ItemsRelationManager's own form,
    // identical shape to TallStackInvoiceForm's.
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

    public function mount(Company $company, ?Invoice $invoice = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding for `invoice` happens before this mount() body
        // runs and before app(Tenancy::class)->set() above activates
        // BelongsToCompany's scope — same explicit re-check as
        // TallStackInvoiceForm::mount() and the PDF controllers. The
        // `is_recurring` check mirrors RecurringInvoiceResource::
        // getRecordRouteBindingEloquentQuery()'s own scope.
        if ($invoice) {
            abort_unless($invoice->company_id === $company->id, 404);
            abort_unless($invoice->is_recurring === true, 404);

            $this->invoice = $invoice->loadMissing(['items.product', 'items.taxes', 'client']);
            $this->client_id = (string) $invoice->client_id;
            $this->number = $invoice->number;
            $this->pricing_mode = $invoice->pricing_mode?->value ?? PricingMode::Exclusive->value;
            $this->po_number = $invoice->po_number;
            $this->currency_code = $invoice->currency_code;
            $this->discount = (float) $invoice->discount;
            $this->discount_is_percentage = $invoice->discount_is_percentage;
            $this->terms = $invoice->terms;
            $this->public_notes = $invoice->public_notes;
            $this->private_notes = $invoice->private_notes;
            $this->footer = $invoice->footer;
            $this->recurring_frequency = $invoice->recurring_frequency ?? 'monthly';
            $this->recurring_start_date = $invoice->recurring_start_date?->toDateString();
            $this->recurring_end_date = $invoice->recurring_end_date?->toDateString();
            $this->no_end_date = blank($this->recurring_end_date);
            $this->auto_bill = (bool) $invoice->auto_bill;

            $this->initializeAutosaveVersion();

            return;
        }

        $this->recurring_start_date = now()->toDateString();
        $this->currency_code = $company->currency_code;
        // Phase 11 (repair plan, decision gate G5): prefill Terms from the
        // company-wide default, never overwriting a real saved value —
        // this whole branch only runs when there is no existing $invoice.
        $this->terms = $company->default_payment_terms;
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

    // --- Draft autosave ---------------------------------------------------
    //
    // Same whitelist and reasoning as TallStackInvoiceForm's own wiring —
    // this template literally is an Invoice row (is_recurring=true, see
    // class docblock), so it reuses the exact same `invoices.draft_version`
    // column. Wired only onto the plain free-text/metadata header fields —
    // po_number, terms, public_notes, private_notes, footer — never
    // client_id, number, pricing_mode, the recurring schedule fields, or
    // the discount fields, since those either go through save()'s own
    // validation/redirect path or feed InvoiceTotalsCalculator's
    // recalculation, which this raw conditional-UPDATE autosave
    // deliberately never triggers.

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
     * A recurring template's `status` carries no enforced state machine
     * (class docblock) — it is set to Draft once at creation and never
     * transitioned away by anything this page (or any other owned action)
     * does, so this check is a stable "always true for a real template"
     * guard rather than a live lifecycle gate. Kept for the same "Auditor
     * is read-only everywhere" boundary save() enforces via its own
     * $this->authorize('update', ...) call, exactly as
     * TallStackInvoiceForm::autosaveGuard() does.
     */
    protected function autosaveGuard(): bool
    {
        return $this->invoice !== null
            && $this->invoice->status === InvoiceStatus::Draft
            && Gate::allows('update', $this->invoice);
    }

    public function save(): void
    {
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
            'currency_code' => ['nullable', 'string', 'max:10'],
            'discount' => ['numeric', 'min:0'],
            'discount_is_percentage' => ['boolean'],
            'terms' => ['nullable', 'string'],
            'public_notes' => ['nullable', 'string'],
            'private_notes' => ['nullable', 'string'],
            'footer' => ['nullable', 'string'],
            'recurring_frequency' => ['required', 'string', 'max:255'],
            'recurring_start_date' => ['nullable', 'date'],
            'recurring_end_date' => ['nullable', 'date'],
            'auto_bill' => ['boolean'],
        ]);

        $payload = [
            'client_id' => $data['client_id'],
            'number' => filled($data['number']) ? $data['number'] : null,
            'pricing_mode' => $this->pricing_mode,
            'po_number' => $this->po_number,
            'currency_code' => $this->currency_code,
            'discount' => $this->discount,
            'discount_is_percentage' => $this->discount_is_percentage,
            'terms' => $this->terms,
            'public_notes' => $this->public_notes,
            'private_notes' => $this->private_notes,
            'footer' => $this->footer,
            'recurring_frequency' => $this->recurring_frequency,
            'recurring_start_date' => $this->recurring_start_date,
            'recurring_end_date' => $this->no_end_date ? null : $this->recurring_end_date,
            'auto_bill' => $this->auto_bill,
        ];

        if ($this->invoice) {
            $this->authorize('update', $this->invoice);

            $this->invoice->update($payload);
            app(InvoiceTotalsCalculator::class)->recalculate($this->invoice);

            $this->toast()->success('Recurring invoice saved.')->send();

            return;
        }

        $this->authorize('create', Invoice::class);

        // Same numbering source as CreateRecurringInvoice::mutateFormDataBeforeCreate()
        // — the template itself consumes the invoice sequence too; each
        // generated instance gets its own separately.
        if (blank($payload['number'])) {
            $payload['number'] = app(DocumentNumberGenerator::class)->next($this->company, 'invoice');
        }

        $payload['company_id'] = $this->company->id;
        $payload['type'] = InvoiceType::Invoice;
        $payload['status'] = InvoiceStatus::Draft;
        $payload['is_recurring'] = true;

        $invoice = Invoice::create($payload);

        $this->toast()->success('Recurring invoice created.', 'Add line items below, then continue the workflow.')->send();

        $this->redirect(route('tallstack.recurring-invoices.edit', [$this->company, $invoice]), navigate: false);
    }

    public function generateNow(): void
    {
        if (! $this->invoice) {
            return;
        }

        $this->authorize('update', $this->invoice);

        $generated = app(InvoiceDuplicator::class)->generateRecurringInstance($this->invoice);

        $this->invoice->refresh();
        $this->toast()->success('Invoice generated', "Created invoice #{$generated->number}.")->send();
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

        $generatedInvoices = $this->invoice
            ? $this->invoice->generatedInvoices()
                ->with('client')
                ->orderByDesc('invoice_date')
                ->get()
                ->map(fn (Invoice $inv) => [
                    'id' => $inv->id,
                    'number' => $inv->number ?? '—',
                    'invoice_date' => $inv->invoice_date?->format('d M Y') ?? '—',
                    'total' => Money::format((float) $inv->total, $inv->currency_code ?: $currency),
                    'status_label' => $inv->status->getLabel(),
                    'status_color' => StatusColor::map($inv->status->getColor()),
                ])
            : collect();

        return view('livewire.tallstack-recurring-invoice-form', [
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'taxRates' => $this->company->taxRates()->orderBy('name')->get(['id', 'name']),
            'pricingModes' => PricingMode::cases(),
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
            'items' => $items,
            'currency' => $currency,
            'generatedInvoices' => $generatedInvoices,
            'subtotal' => $this->invoice ? Money::format((float) $this->invoice->subtotal, $currency) : Money::format(0, $currency),
            'taxTotal' => $this->invoice ? Money::format((float) $this->invoice->tax_total, $currency) : Money::format(0, $currency),
            'total' => $this->invoice ? Money::format((float) $this->invoice->total, $currency) : Money::format(0, $currency),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'recurring-invoices',
            'title' => $this->invoice ? ($this->invoice->number ?? 'Recurring invoice') : 'New recurring invoice',
        ]);
    }
}
