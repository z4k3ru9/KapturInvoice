<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row of a vendor's own pricelist spreadsheet (e.g. Hikvision/HiLook
 * dealer pricelists), imported/refreshed via App\Services\PriceListImporter
 * — see docs/price-list-import.md. This is a *reference* catalog, not the
 * company's sellable one (that's `Product`); App\Services\ProductSync
 * ("Create/update product" row action on the Price List resource) copies
 * sku/description/unit_cost across to create or refresh an actual Product
 * from a chosen row, linked back via `products.price_list_item_id`.
 */
#[Fillable([
    'company_id', 'brand', 'category', 'sku', 'description',
    'prices', 'reference_price', 'source_file', 'imported_at',
])]
class PriceListItem extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'prices' => 'array',
            'reference_price' => 'decimal:4',
            'imported_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
