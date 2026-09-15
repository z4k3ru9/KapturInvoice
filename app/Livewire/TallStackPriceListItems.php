<?php

namespace App\Livewire;

use App\Filament\Support\Money;
use App\Models\Company;
use App\Models\PriceListItem;
use App\Services\PriceListImporter;
use App\Services\ProductSync;
use Filament\Facades\Filament;
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
 * App\Services\ProductSync exactly as
 * App\Filament\Resources\PriceListItems already does — this is a
 * presentation-layer swap only, deliberately register/list-only per
 * prompt 14's own framing ("browse the vendor's current price sheet, then
 * create/refresh a real Product from a chosen row" — never edit the
 * pricelist rows by hand). The Filament resource's manual create/edit
 * form (PriceListItemForm) is a one-off fallback there for hand-typed
 * rows; this page doesn't build it, since the Stitch mockup itself never
 * shows one either.
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

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as every other TALL-stack page's mount() — kept
        // for parity even though nothing on this page calls
        // Resource::getUrl() today.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);
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
            'file' => ['required', 'file', 'mimes:xlsx'],
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
            'currency' => $currency,
            'hasAnyItems' => PriceListItem::query()->where('company_id', $this->company->id)->exists(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'price-list-items',
            'title' => 'Price List Items',
        ]);
    }
}
