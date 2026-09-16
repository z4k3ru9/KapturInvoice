<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Services\PriceListImporter;
use App\Services\ProductSync;
use App\Support\Dashboard\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Price List Items register — see App\Livewire\TallStackProducts's
 * docblock for the established pattern this follows. Reuses
 * App\Models\PriceListItem, App\Services\PriceListImporter, and
 * App\Services\ProductSync exactly as the equivalent pre-TallStackUI
 * Filament price list items resource already did — this is a
 * presentation-layer swap only, deliberately register/list-only per
 * prompt 14's own framing ("browse the vendor's current price sheet, then
 * create/refresh a real Product from a chosen row" — never edit the
 * pricelist rows by hand). The Filament resource's manual create/edit
 * form (PriceListItemForm) is a one-off fallback there for hand-typed
 * rows; this page doesn't build it, since the Stitch mockup itself never
 * shows one either.
 *
 * One deliberate, narrow exception (repair plan Phase 10a): `category` is
 * hand-editable via a small "Edit category" modal
 * (openEditCategory()/saveCategory()) — every imported sheet's own
 * header-detection logic already guesses it per
 * App\Services\PriceListImporter, and it's frequently wrong or absent, so
 * a way to correct it without re-importing the whole sheet is worth the
 * one-field exception. Every other column (brand/sku/description/price)
 * stays a pure reflection of the last import, unchanged.
 *
 * `category` shares one taxonomy with `Product::category` per decision
 * gate G3 (repair plan Phase 10b) — render()'s `categories` option list
 * merges both tables' distinct values, not just this one's, so a category
 * introduced from either side offers itself on the other.
 */
#[Layout('components.tallstack.app')]
class TallStackPriceListItems extends Component
{
    use Interactions, WithFileUploads, WithPagination;

    public Company $company;

    public ?string $brandFilter = null;

    public string $search = '';

    // --- Import modal state — matches ListPriceListItems's own header action fields. ---
    public bool $showImportModal = false;

    public mixed $file = null;

    public ?string $importBrand = null;

    // --- Edit-category modal state — see this class's own docblock for
    // why `category` specifically is the one hand-editable field here. ---
    public bool $showCategoryModal = false;

    public ?int $editingCategoryId = null;

    public ?string $category = null;

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

    public function filterBrand(?string $brand): void
    {
        $this->brandFilter = $brand;
        $this->resetPage();
    }

    public function openImportModal(): void
    {
        $this->authorize('create', PriceListItem::class);

        $this->file = null;
        $this->importBrand = null;
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    /** Reuses App\Services\PriceListImporter exactly as ListPriceListItems's own "Import pricelist" header action does. */
    public function import(): void
    {
        $this->authorize('create', PriceListItem::class);

        $data = $this->validate([
            // Type was already constrained (xlsx only); size was not — matches
            // the 10MB ceiling App\Livewire\Concerns\ManagesDocuments already
            // uses for every other upload in the app. Generous for a real
            // pricelist spreadsheet (a few hundred rows at most, see
            // docs/price-list-import.md) while bounding how large a file this
            // still has to parse in memory (App\Services\PriceListImporter).
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'importBrand' => ['required', 'string', 'max:255'],
        ]);

        $storedPath = $data['file']->store('price-list-imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $stats = app(PriceListImporter::class)->import(
            $absolutePath,
            $this->company,
            $data['importBrand'],
            basename($storedPath)
        );

        Storage::disk('local')->delete($storedPath);

        $body = "{$stats['rows_read']} rows ({$stats['created']} new, {$stats['updated']} updated).";

        if ($stats['sheets_skipped'] !== []) {
            $body .= ' Sheets skipped (no recognizable pricelist table): '.implode(', ', $stats['sheets_skipped']).'.';
        }

        $this->toast()->success('Pricelist imported', $body)->send();

        $this->showImportModal = false;
        $this->file = null;
        $this->importBrand = null;
        $this->resetPage();
    }

    /** Reuses App\Services\ProductSync exactly as PriceListItemsTable's own "Create/update product" row action does. */
    public function syncProduct(int $id): void
    {
        $item = $this->findScoped($id);

        if (! $item) {
            return;
        }

        $this->authorize('update', $item);

        $product = app(ProductSync::class)->createOrUpdateFromPriceListItem($item);

        $this->toast()->success(
            $product->wasRecentlyCreated ? 'Product created' : 'Product updated',
            "Product #{$product->id} ({$product->sku}) is now linked to this pricelist row.",
        )->send();
    }

    /** Opens the "Edit category" modal — uses <x-tallstack.category-select> (resources/views/components/tallstack/category-select.blade.php, repair plan Phase 10a)'s searchable/inline-create picker, sourced from every distinct category already used on this company's price list items. */
    public function openEditCategory(int $id): void
    {
        $item = $this->findScoped($id);

        if (! $item) {
            return;
        }

        $this->authorize('update', $item);

        $this->editingCategoryId = $item->id;
        $this->category = $item->category;
        $this->resetErrorBag();
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $item = $this->findScoped($this->editingCategoryId);

        if (! $item) {
            return;
        }

        $this->authorize('update', $item);

        $data = $this->validate([
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $item->update(['category' => filled($data['category']) ? trim($data['category']) : null]);

        $this->toast()->success('Category updated')->send();

        $this->showCategoryModal = false;
        $this->editingCategoryId = null;
        $this->category = null;
    }

    /** Never trust a bare `PriceListItem::find()` — always re-check company ownership explicitly, same as every other TALL-stack page. */
    private function findScoped(?int $id): ?PriceListItem
    {
        if (! $id) {
            return null;
        }

        $item = PriceListItem::find($id);

        if (! $item || $item->company_id !== $this->company->id) {
            return null;
        }

        return $item;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $items = PriceListItem::query()
            ->where('company_id', $this->company->id)
            ->withCount('products')
            ->when($this->brandFilter, fn ($q) => $q->where('brand', $this->brandFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('sku', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->orderBy('sku')
            ->paginate(15)
            ->through(fn (PriceListItem $item) => [
                'id' => $item->id,
                'brand' => $item->brand,
                'category' => $item->category,
                'sku' => $item->sku,
                'description' => $item->description,
                'price' => $item->reference_price !== null
                    ? Money::format((float) $item->reference_price, $currency)
                    : '—',
                'updated_relative' => $item->imported_at?->diffForHumans() ?? $item->updated_at->diffForHumans(),
                'linked' => $item->products_count > 0,
            ]);

        return view('livewire.tallstack-price-list-items', [
            'items' => $items,
            'brands' => PriceListItem::query()
                ->where('company_id', $this->company->id)
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand'),
            // Powers <x-tallstack.category-select>'s picker with the
            // SHARED taxonomy per decision gate G3 (repair plan Phase
            // 10b) — every distinct category already used on this
            // tenant's Price List Items *and* its Products, not just this
            // table's own values, otherwise a category created from the
            // Products side would never round-trip back here even though
            // both pickers claim to share one list. Mirrors
            // TallStackProducts::render()'s equivalent query.
            'categories' => PriceListItem::query()
                ->where('company_id', $this->company->id)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->merge(
                    Product::query()
                        ->where('company_id', $this->company->id)
                        ->whereNotNull('category')
                        ->distinct()
                        ->pluck('category')
                )
                ->unique()
                ->sort()
                ->values(),
            'currency' => $currency,
            'hasAnyItems' => PriceListItem::query()->where('company_id', $this->company->id)->exists(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'price-list-items',
            'title' => 'Price List Items',
        ]);
    }
}
