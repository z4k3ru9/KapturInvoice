<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackPriceListItems;
use App\Models\Company;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Covers the TALL-stack Price List Items register
 * (App\Livewire\TallStackPriceListItems) — same shape as the
 * pre-TallStackUI Filament admin's equivalent test, proving the
 * import and "Create/update product" actions reuse the exact same
 * App\Services\PriceListImporter/App\Services\ProductSync the Filament
 * resource uses, and that a cross-company row is never reachable.
 */
class TallStackPriceListItemsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $this->actingAs($this->user);
    }

    private function buildFixture(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Model', 'Description', 'Dealer price', 'MSRP']));
        $writer->addRow(Row::fromValues(['CAM-100', 'A basic camera', 100.0, 150.0]));
        $writer->close();
    }

    public function test_import_uploads_and_parses_a_pricelist_file(): void
    {
        Storage::fake('local');

        $fixturePath = tempnam(sys_get_temp_dir(), 'pricelist').'.xlsx';
        $this->buildFixture($fixturePath);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->set('file', UploadedFile::fake()->createWithContent('fixture.xlsx', file_get_contents($fixturePath)))
            ->set('importBrand', 'Hikvision')
            ->call('import')
            ->assertHasNoErrors();

        $item = PriceListItem::where('company_id', $this->company->id)->where('sku', 'CAM-100')->first();

        $this->assertNotNull($item);
        $this->assertSame('Hikvision', $item->brand);
        $this->assertSame('A basic camera', $item->description);
        $this->assertSame('100.0000', $item->reference_price);

        @unlink($fixturePath);
    }

    public function test_sync_product_creates_and_then_refreshes_the_linked_product(): void
    {
        $item = PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-100',
            'description' => 'A basic camera',
            'reference_price' => 100,
        ]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->call('syncProduct', $item->id);

        $product = Product::where('price_list_item_id', $item->id)->first();

        $this->assertNotNull($product);
        $this->assertSame('CAM-100', $product->sku);
        $this->assertSame('100.0000', (string) $product->unit_cost);

        // Re-running it refreshes the same Product rather than duplicating it.
        $item->update(['reference_price' => 120]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->call('syncProduct', $item->id);

        $this->assertSame(1, Product::where('price_list_item_id', $item->id)->count());
        $this->assertSame('120.0000', (string) $product->fresh()->unit_cost);
    }

    public function test_a_pricelist_item_from_another_company_is_never_synced(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $foreignItem = PriceListItem::create([
            'company_id' => $otherCompany->id,
            'brand' => 'Hikvision',
            'sku' => 'NOT-YOURS',
            'reference_price' => 50,
        ]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->call('syncProduct', $foreignItem->id);

        $this->assertNull(Product::where('price_list_item_id', $foreignItem->id)->first());
    }

    public function test_the_register_only_lists_the_active_companys_rows(): void
    {
        PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-100',
            'reference_price' => 100,
        ]);

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        PriceListItem::create([
            'company_id' => $otherCompany->id,
            'brand' => 'Hikvision',
            'sku' => 'NOT-YOURS',
            'reference_price' => 50,
        ]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->assertSee('CAM-100')
            ->assertDontSee('NOT-YOURS');
    }

    public function test_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        Livewire::test(TallStackPriceListItems::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }

    public function test_save_category_updates_the_category_on_the_scoped_item(): void
    {
        $item = PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-100',
            'reference_price' => 100,
        ]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->call('openEditCategory', $item->id)
            ->assertSet('editingCategoryId', $item->id)
            ->set('category', 'IP Cameras')
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertSet('showCategoryModal', false);

        $this->assertSame('IP Cameras', $item->fresh()->category);
    }

    public function test_save_category_can_clear_the_category_back_to_null(): void
    {
        $item = PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-100',
            'category' => 'IP Cameras',
            'reference_price' => 100,
        ]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->call('openEditCategory', $item->id)
            ->set('category', '')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertNull($item->fresh()->category);
    }

    public function test_a_pricelist_item_from_another_company_cannot_have_its_category_edited(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $foreignItem = PriceListItem::create([
            'company_id' => $otherCompany->id,
            'brand' => 'Hikvision',
            'sku' => 'NOT-YOURS',
            'category' => 'Original',
            'reference_price' => 50,
        ]);

        Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->call('openEditCategory', $foreignItem->id)
            ->assertSet('showCategoryModal', false);

        $this->assertSame('Original', $foreignItem->fresh()->category);
    }

    public function test_the_category_picker_only_offers_the_active_companys_distinct_categories(): void
    {
        PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-100',
            'category' => 'IP Cameras',
            'reference_price' => 100,
        ]);
        PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-101',
            'category' => 'IP Cameras',
            'reference_price' => 110,
        ]);

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        PriceListItem::create([
            'company_id' => $otherCompany->id,
            'brand' => 'Hikvision',
            'sku' => 'NOT-YOURS',
            'category' => 'Other Company Category',
            'reference_price' => 50,
        ]);

        $categories = Livewire::test(TallStackPriceListItems::class, ['company' => $this->company])
            ->viewData('categories');

        $this->assertSame(['IP Cameras'], $categories->values()->all());
    }
}
