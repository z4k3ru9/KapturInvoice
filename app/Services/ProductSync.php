<?php

namespace App\Services;

use App\Models\PriceListItem;
use App\Models\Product;

/**
 * Turns a PriceListItem reference-catalog row into (or refreshes) an
 * actual, invoiceable Product — this is what makes the imported vendor
 * pricelist "the main pricelist reference for most of my items" per
 * docs/price-list-import.md, rather than just a read-only browse table.
 *
 * `products.price_list_item_id` links the two, so re-running this against
 * an already-linked Product refreshes its sku/name/description/unit_cost
 * from the (presumably just re-imported) pricelist row instead of creating
 * a duplicate — the same "update this regularly" upsert spirit as
 * PriceListImporter itself, one step further down the chain.
 */
class ProductSync
{
    public function createOrUpdateFromPriceListItem(PriceListItem $item): Product
    {
        $product = Product::query()
            ->where('company_id', $item->company_id)
            ->where('price_list_item_id', $item->id)
            ->first() ?? new Product;

        $product->fill([
            'company_id' => $item->company_id,
            'price_list_item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->sku,
            'description' => $item->description,
            'unit_cost' => $item->reference_price ?? 0,
        ]);

        $product->save();

        return $product;
    }
}
