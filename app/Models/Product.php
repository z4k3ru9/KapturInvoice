<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Enums\TaxCategory;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A company's sellable catalog item — despite the class name, `type` (see
 * `App\Enums\CatalogItemType`) means this models any quotation/invoice
 * line source: a physical product, a service, labor, or a miscellaneous
 * "other" line, not only physical goods. Kept as `Product`/`products`
 * (not renamed to `CatalogItem`) per docs/REFACTOR_PLAN.md §1.1 — the
 * class already had the right shape (a sellable line distinct from the
 * vendor reference catalog, `PriceListItem`), so Phase 02
 * (docs/rebuild/specs/02-parties-and-catalog/Specs.md) only needed to add
 * the type/unit/tax-category/stock fields, not restructure the table.
 * `unit_cost` is this item's default *selling* price (an inherited naming
 * quirk from the legacy schema, same as InvoiceNinja's own `products.cost`
 * — it is copied straight onto `invoice_items.unit_cost`, never treated as
 * a vendor cost), and `stock_flag` is a label only — it must never drive
 * an "in stock"/availability computation; full inventory is deferred
 * launch scope.
 */
#[Fillable([
    'company_id', 'legacy_product_id', 'sku', 'image_path', 'name', 'type', 'unit', 'description',
    'unit_cost', 'default_tax_rate_id', 'tax_category', 'stock_flag', 'price_list_item_id',
])]
class Product extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'unit_cost' => 'decimal:4',
            'tax_category' => TaxCategory::class,
            'stock_flag' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultTaxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function priceListItem(): BelongsTo
    {
        return $this->belongsTo(PriceListItem::class);
    }

    /**
     * Same reasoning as Company::getLogoDataUri() — dompdf can't fetch a
     * Storage::url() for the `local` disk, so PDFs (and anything else that
     * needs a self-contained image, like a Proposal Snippet generated from
     * this product) embed the picture as a base64 data URI instead.
     */
    public function getImageDataUri(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        try {
            $disk = Storage::disk(config('filesystems.default'));

            if (! $disk->exists($this->image_path)) {
                return null;
            }

            $mimeType = $disk->mimeType($this->image_path) ?: 'image/png';

            return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($this->image_path));
        } catch (Throwable) {
            return null;
        }
    }
}
