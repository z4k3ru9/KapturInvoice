<?php

namespace App\Livewire;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\JobType;
use App\Enums\PricingMode;
use App\Enums\QuotationStatus;
use App\Filament\Support\Money;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\DocumentNumberGenerator;
use App\Services\QuotationTotalsCalculator;
use App\Support\TallStack\StatusColor;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack create/edit line editor for a Quotation — the sibling
 * page to App\Livewire\TallStackQuotations. Mirrors
 * App\Filament\Resources\Quotations\Schemas\QuotationForm (header fields)
 * and ...\RelationManagers\ItemsRelationManager (line items) field-for-
 * field: no field is added or dropped, and `status` is never a form field
 * here either — every transition goes through the exact same
 * App\Actions\Sales\* classes the Filament table's row actions call.
 *
 * A quotation must exist before it can carry line items (line items
 * belong to a `quotation_id`) — same reason
 * App\Filament\Resources\Quotations\Pages\CreateQuotation redirects into
 * the Edit page immediately after creating. This component mirrors that:
 * saving a brand-new quotation redirects into its own edit route before
 * the items table becomes available.
 */
#[Layout('components.tallstack.app')]
class TallStackQuotationForm extends Component
{
    use Interactions;

    public Company $company;

    public ?Quotation $quotation = null;

    // Header fields — exactly QuotationForm's own field set.
    public ?string $client_id = null;

    public ?string $number = null;

    public string $pricing_mode = PricingMode::Exclusive->value;

    public string $job_type = JobType::Installation->value;

    public ?string $quotation_date = null;

    public ?string $valid_until = null;

    public float $discount = 0;

    public bool $discount_is_percentage = false;

    public ?string $terms = null;

    public ?string $notes = null;

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

    // Accept modal state — same shape as TallStackQuotations::accept().
    public bool $showAcceptModal = false;

    public ?string $customerPoNumber = null;

    public ?string $customerPoDate = null;

    public function mount(Company $company, ?Quotation $quotation = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        // Route binding for `quotation` happens before this mount() body
        // runs and before Filament::setTenant() above activates
        // BelongsToCompany's scope — so, exactly like
        // App\Http\Controllers\QuotationPdfController, cross-company
        // access is checked explicitly here rather than trusted to the
        // (at binding time, inactive) global scope.
        if ($quotation) {
            abort_unless($quotation->company_id === $company->id, 404);

            $this->quotation = $quotation->loadMissing(['items.product', 'client']);
            $this->client_id = (string) $quotation->client_id;
            $this->number = $quotation->number;
            $this->pricing_mode = $quotation->pricing_mode->value;
            $this->job_type = $quotation->job_type->value;
            $this->quotation_date = $quotation->quotation_date?->toDateString();
            $this->valid_until = $quotation->valid_until?->toDateString();
            $this->discount = (float) $quotation->discount;
            $this->discount_is_percentage = $quotation->discount_is_percentage;
            $this->terms = $quotation->terms;
            $this->notes = $quotation->notes;

            return;
        }

        $this->quotation_date = now()->toDateString();
    }

    /** Same client-default-discount prefill as QuotationForm's own afterStateUpdated(). */
    public function updatedClientId(?string $value): void
    {
        if (! $value || $this->quotation) {
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
            'job_type' => ['required'],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'discount' => ['numeric', 'min:0'],
            'discount_is_percentage' => ['boolean'],
            'terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'client_id' => $data['client_id'],
            'number' => filled($data['number']) ? $data['number'] : null,
            'pricing_mode' => $this->pricing_mode,
            'job_type' => $this->job_type,
            'quotation_date' => $this->quotation_date,
            'valid_until' => $this->valid_until,
            'discount' => $this->discount,
            'discount_is_percentage' => $this->discount_is_percentage,
            'terms' => $this->terms,
            'notes' => $this->notes,
        ];

        if ($this->quotation) {
            $this->authorize('update', $this->quotation);

            $this->quotation->update($payload);
            app(QuotationTotalsCalculator::class)->recalculate($this->quotation);

            $this->toast()->success('Quotation saved.')->send();

            return;
        }

        $this->authorize('create', Quotation::class);

        if (blank($payload['number'])) {
            $payload['number'] = app(DocumentNumberGenerator::class)->next($this->company, 'quote');
        }

        $payload['company_id'] = $this->company->id;
        $payload['status'] = QuotationStatus::Draft;

        $quotation = Quotation::create($payload);

        $this->toast()->success('Quotation created.', 'Add line items below, then continue the workflow.')->send();

        $this->redirect(route('tallstack.quotations.edit', [$this->company, $quotation]), navigate: false);
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
        $this->showItemModal = true;
    }

    /** Same product -> title/unit_cost autofill as ItemsRelationManager's afterStateUpdated(). */
    public function updatedItemProductId(?string $value): void
    {
        if (! $value) {
            return;
        }

        if ($product = Product::query()->where('company_id', $this->company->id)->find($value)) {
            $this->item_title = $product->name;
            $this->item_unit_cost = (float) $product->unit_cost;
        }
    }

    public function saveItem(): void
    {
        if (! $this->quotation) {
            return;
        }

        $this->authorize('update', $this->quotation);

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

        if ($this->editingItemId) {
            $item = $this->scopedItem($this->editingItemId);

            if ($item) {
                $item->update($payload);
            }
        } else {
            $payload['quotation_id'] = $this->quotation->id;
            $payload['sort_order'] = ((int) $this->quotation->items()->max('sort_order')) + 1;
            QuotationItem::create($payload);
        }

        app(QuotationTotalsCalculator::class)->recalculate($this->quotation);
        $this->quotation->refresh()->load('items.product');

        $this->showItemModal = false;
        $this->resetItemForm();
        $this->toast()->success('Line item saved.')->send();
    }

    public function deleteItem(int $id): void
    {
        if (! $this->quotation) {
            return;
        }

        $this->authorize('update', $this->quotation);

        $item = $this->scopedItem($id);

        if (! $item) {
            return;
        }

        $item->delete();
        app(QuotationTotalsCalculator::class)->recalculate($this->quotation);
        $this->quotation->refresh()->load('items.product');

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
    }

    private function scopedItem(int $id): ?QuotationItem
    {
        if (! $this->quotation) {
            return null;
        }

        return $this->quotation->items->firstWhere('id', $id);
    }

    // --- Status transitions — identical to TallStackQuotations, scoped to this record. -----

    public function approve(): void
    {
        $this->applyTransition(QuotationStatus::Approved);
    }

    public function send(): void
    {
        $this->applyTransition(QuotationStatus::Sent);
    }

    public function reject(): void
    {
        $this->applyTransition(QuotationStatus::Rejected);
    }

    public function markExpired(): void
    {
        $this->applyTransition(QuotationStatus::Expired);
    }

    public function cancel(): void
    {
        $this->applyTransition(QuotationStatus::Cancelled);
    }

    public function openAcceptModal(): void
    {
        $this->customerPoNumber = null;
        $this->customerPoDate = null;
        $this->showAcceptModal = true;
    }

    public function accept(): void
    {
        if (! $this->quotation) {
            return;
        }

        $this->authorize('update', $this->quotation);

        try {
            app(AcceptQuotation::class)->accept(
                $this->quotation,
                filled($this->customerPoNumber) ? $this->customerPoNumber : null,
                filled($this->customerPoDate) ? Carbon::parse($this->customerPoDate) : null,
            );

            $this->quotation->refresh();
            $this->showAcceptModal = false;
            $this->toast()->success('Quotation accepted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not accept quotation', $e->getMessage())->send();
        }
    }

    public function createJob(): void
    {
        if (! $this->quotation) {
            return;
        }

        $this->authorize('update', $this->quotation);

        try {
            $salesOrder = app(CreateSalesOrderFromQuotation::class)->create($this->quotation);

            $this->quotation->refresh();
            $this->toast()->success('Job created', "Created job #{$salesOrder->number}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not create job', $e->getMessage())->send();
        }
    }

    private function applyTransition(QuotationStatus $to): void
    {
        if (! $this->quotation) {
            return;
        }

        $this->authorize('update', $this->quotation);

        try {
            app(TransitionQuotationStatus::class)->transition($this->quotation, $to);
            $this->quotation->refresh();
            $this->toast()->success('Quotation updated.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not update quotation', $e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $items = $this->quotation
            ? $this->quotation->items->sortBy('sort_order')->map(fn (QuotationItem $item) => [
                'id' => $item->id,
                'image' => $item->product?->getImageDataUri(),
                'title' => $item->title,
                'quantity' => (float) $item->quantity,
                'unit_cost' => Money::format((float) $item->unit_cost, $currency),
                'line_total' => Money::format((float) $item->line_total, $currency),
            ])->values()
            : collect();

        return view('livewire.tallstack-quotation-form', [
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'pricingModes' => PricingMode::cases(),
            'jobTypes' => JobType::cases(),
            'items' => $items,
            'currency' => $currency,
            'statusColor' => $this->quotation ? StatusColor::map($this->quotation->status->getColor()) : null,
            'subtotal' => $this->quotation ? Money::format((float) $this->quotation->subtotal, $currency) : Money::format(0, $currency),
            'total' => $this->quotation ? Money::format((float) $this->quotation->total, $currency) : Money::format(0, $currency),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'quotations',
            'title' => $this->quotation ? "Quotation {$this->quotation->number}" : 'New quotation',
        ]);
    }
}
