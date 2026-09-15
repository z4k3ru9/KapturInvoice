<?php

namespace App\Livewire;

use App\Enums\CatalogItemType;
use App\Enums\TaxCategory;
use App\Filament\Support\Money;
use App\Models\Company;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\ProposalSnippetSync;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Products/Catalog register — see App\Livewire\TallStackQuotations's
 * docblock for the established pattern this follows. Reuses
 * App\Models\Product, App\Enums\CatalogItemType/TaxCategory, and
 * App\Services\ProposalSnippetSync exactly as
 * App\Filament\Resources\Products already does — this is a
 * presentation-layer swap only.
 *
 * Create/Edit is a modal on this same list page, not a separate route —
 * Products is one of the 14 resources CLAUDE.md documents as
 * modal-based Create/Edit in Filament (ProductResource::getPages() omits
 * 'create'/'edit'), and the fetched Stitch mockup ("Products - Picture
 * Upload & Thumbnail Placement") itself renders the edit form as a modal
 * dialog over the register table, confirming rather than overriding that
 * choice.
 *
 * The picture upload is a plain Livewire temporary-upload
 * (`Livewire\WithFileUploads`), stored to the exact same `'products'`
 * directory / default filesystem disk `Product::image_path` and
 * `App\Filament\Resources\Products\Schemas\ProductForm`'s own
 * `FileUpload::make('image_path')->directory('products')` already use —
 * `Product::getImageDataUri()` (base64 data URI) is reused unmodified to
 * render the thumbnail, never a `Storage::url()`.
 */
#[Layout('components.tallstack.app')]
class TallStackProducts extends Component
{
    use Interactions, WithFileUploads, WithPagination;

    public Company $company;

    public ?string $typeFilter = null;

    public string $search = '';

    // --- Create/Edit modal state — field-for-field ProductForm's own set. ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public ?string $name = null;

    public ?string $sku = null;

    public string $type = CatalogItemType::Product->value;

    public ?string $unit = null;

    public float $unit_cost = 0;

    public string $tax_category = TaxCategory::StandardTaxable->value;

    public ?string $default_tax_rate_id = null;

    public bool $stock_flag = false;

    public ?string $description = null;

    /** New picture staged for upload this save — null keeps the existing image_path untouched. */
    public mixed $photo = null;

    /** The product's current stored image_path, shown as a preview; cleared by removePhoto(). */
    public ?string $existingImagePath = null;

    /** Product::getImageDataUri() for $existingImagePath, computed once in openEditModal() rather than re-queried on every render. */
    public ?string $existingImageDataUri = null;

    /** Read-only display only — never a form field (ProductForm has none for this either). */
    public ?int $legacyProductId = null;

    public ?string $priceListItemLabel = null;

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as every other TALL-stack page's mount() — kept
        // for parity even though nothing on this page calls
        // Resource::getUrl() today.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function filterType(?string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Product::class);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $product = $this->findScoped($id);

        if (! $product) {
            return;
        }

        $this->authorize('update', $product);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->type = $product->type->value;
        $this->unit = $product->unit;
        $this->unit_cost = (float) $product->unit_cost;
        $this->tax_category = $product->tax_category->value;
        $this->default_tax_rate_id = $product->default_tax_rate_id ? (string) $product->default_tax_rate_id : null;
        $this->stock_flag = (bool) $product->stock_flag;
        $this->description = $product->description;
        $this->photo = null;
        $this->existingImagePath = $product->image_path;
        $this->existingImageDataUri = $product->getImageDataUri();
        $this->legacyProductId = $product->legacy_product_id;
        $this->priceListItemLabel = $product->priceListItem
            ? trim(($product->priceListItem->brand ?? '').' '.($product->priceListItem->sku ?? ''))
            : null;

        $this->showFormModal = true;
    }

    /** Clears the staged-for-removal picture — save() then leaves image_path null. */
    public function removePhoto(): void
    {
        $this->photo = null;
        $this->existingImagePath = null;
        $this->existingImageDataUri = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CatalogItemType::class)],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'tax_category' => ['required', Rule::enum(TaxCategory::class)],
            'default_tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $this->company->id)],
            'stock_flag' => ['boolean'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $payload = [
            'name' => $data['name'],
            'sku' => $data['sku'],
            'type' => $this->type,
            'unit' => $data['unit'],
            'unit_cost' => $data['unit_cost'],
            'tax_category' => $this->tax_category,
            'default_tax_rate_id' => $data['default_tax_rate_id'],
            'stock_flag' => $this->stock_flag,
            'description' => $data['description'],
        ];

        // A newly staged photo always wins; otherwise keep whatever
        // existingImagePath currently holds (untouched, or null if
        // removePhoto() was clicked) — same "leave blank keeps current"
        // shape as the rest of this app's optional uploads.
        if ($this->photo) {
            $payload['image_path'] = $this->photo->store('products', config('filesystems.default'));
        } else {
            $payload['image_path'] = $this->existingImagePath;
        }

        if ($this->editingId) {
            $product = $this->findScoped($this->editingId);

            if (! $product) {
                return;
            }

            $this->authorize('update', $product);

            $oldImagePath = $product->image_path;
            $product->update($payload);

            if ($oldImagePath && $oldImagePath !== $payload['image_path']) {
                Storage::disk(config('filesystems.default'))->delete($oldImagePath);
            }

            $this->toast()->success('Product saved.')->send();
        } else {
            $this->authorize('create', Product::class);

            $payload['company_id'] = $this->company->id;
            Product::create($payload);

            $this->toast()->success('Product created.')->send();
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $product = $this->findScoped($id);

        if (! $product) {
            return;
        }

        $this->authorize('delete', $product);

        $product->delete();

        $this->toast()->success('Product removed.')->send();
    }

    /** Reuses App\Services\ProposalSnippetSync exactly as ProductsTable's own row action does. */
    public function createProposalSnippet(int $id): void
    {
        $product = $this->findScoped($id);

        if (! $product) {
            return;
        }

        $this->authorize('update', $product);

        if (blank($product->image_path)) {
            return;
        }

        $snippet = app(ProposalSnippetSync::class)->createOrUpdateFromProduct($product);

        $this->toast()->success(
            $snippet->wasRecentlyCreated ? 'Proposal snippet created.' : 'Proposal snippet updated.',
            "Copy \"{$snippet->name}\" from Proposal Snippets into a proposal's content.",
        )->send();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = null;
        $this->sku = null;
        $this->type = CatalogItemType::Product->value;
        $this->unit = null;
        $this->unit_cost = 0;
        $this->tax_category = TaxCategory::StandardTaxable->value;
        $this->default_tax_rate_id = null;
        $this->stock_flag = false;
        $this->description = null;
        $this->photo = null;
        $this->existingImagePath = null;
        $this->existingImageDataUri = null;
        $this->legacyProductId = null;
        $this->priceListItemLabel = null;
        $this->resetErrorBag();
    }

    /** Never trust a bare `Product::find()` — always re-check company ownership explicitly, same as every other TALL-stack page. */
    private function findScoped(?int $id): ?Product
    {
        if (! $id) {
            return null;
        }

        $product = Product::find($id);

        if (! $product || $product->company_id !== $this->company->id) {
            return null;
        }

        return $product;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $products = Product::query()
            ->where('company_id', $this->company->id)
            ->with('defaultTaxRate')
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'image' => $product->getImageDataUri(),
                'sku' => $product->sku ?? '—',
                'name' => $product->name,
                'type' => $product->type,
                'type_label' => $product->type->getLabel(),
                'type_color' => StatusColor::map($product->type->getColor()),
                'unit' => $product->unit ?? '—',
                'price' => Money::format((float) $product->unit_cost, $currency),
                'tax_category_label' => $product->tax_category->getLabel(),
                'tax_category_color' => $product->tax_category === TaxCategory::StandardTaxable ? 'blue' : 'gray',
                'stock_flag' => (bool) $product->stock_flag,
                'has_image' => filled($product->image_path),
            ]);

        return view('livewire.tallstack-products', [
            'products' => $products,
            'types' => CatalogItemType::cases(),
            'taxCategories' => TaxCategory::cases(),
            'taxRates' => TaxRate::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'currency' => $currency,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'products',
            'title' => 'Products',
        ]);
    }
}
