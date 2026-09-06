<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Services\ProductSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_product_linked_to_the_pricelist_row(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $item = PriceListItem::create([
            'company_id' => $company->id,
            'brand' => 'Hikvision',
            'sku' => 'DS-7104NI-Q1/M',
            'description' => 'A 4-channel NVR',
            'reference_price' => 1187100,
        ]);

        $product = app(ProductSync::class)->createOrUpdateFromPriceListItem($item);

        $this->assertTrue($product->wasRecentlyCreated);
        $this->assertSame($company->id, $product->company_id);
        $this->assertSame($item->id, $product->price_list_item_id);
        $this->assertSame('DS-7104NI-Q1/M', $product->sku);
        $this->assertSame('A 4-channel NVR', $product->description);
        $this->assertSame('1187100.0000', (string) $product->unit_cost);
    }

    public function test_refreshes_the_same_product_instead_of_duplicating_it(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $item = PriceListItem::create([
            'company_id' => $company->id,
            'brand' => 'Hikvision',
            'sku' => 'DS-7104NI-Q1/M',
            'reference_price' => 100,
        ]);

        $first = app(ProductSync::class)->createOrUpdateFromPriceListItem($item);

        $item->update(['reference_price' => 150]);
        $second = app(ProductSync::class)->createOrUpdateFromPriceListItem($item);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Product::where('price_list_item_id', $item->id)->count());
        $this->assertSame('150.0000', (string) $second->fresh()->unit_cost);
    }
}
