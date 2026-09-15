<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PriceListItems\Pages\ListPriceListItems;
use App\Models\Company;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Covers the admin-panel side of the Price List feature (see
 * docs/price-list-import.md): the self-service "Import" header action
 * (App\Services\PriceListImporter, driven from the UI instead of
 * `import:pricelist`) and the "Create/update product" row action
 * (App\Services\ProductSync) that links a reference row to a real,
 * invoiceable Product. Parser behavior itself is exercised in
 * tests/Feature/Services/PriceListImporterTest.php — this test only
 * checks the two actions are wired correctly.
 */
class PriceListItemResourceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    private function buildFixture(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Model', 'Description', 'Dealer price', 'MSRP']));
        $writer->addRow(Row::fromValues(['CAM-100', 'A basic camera', 100.0, 150.0]));
        $writer->close();
    }

    public function test_import_action_uploads_and_parses_a_pricelist_file(): void
    {
        Storage::fake('local');

        $fixturePath = tempnam(sys_get_temp_dir(), 'pricelist').'.xlsx';
        $this->buildFixture($fixturePath);

        Livewire::test(ListPriceListItems::class)
            ->mountAction('import')
            ->setActionData([
                'file' => UploadedFile::fake()->createWithContent('fixture.xlsx', file_get_contents($fixturePath)),
                'brand' => 'Hikvision',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $item = PriceListItem::where('company_id', $this->company->id)->where('sku', 'CAM-100')->first();

        $this->assertNotNull($item);
        $this->assertSame('Hikvision', $item->brand);
        $this->assertSame('A basic camera', $item->description);
        $this->assertSame('100.0000', $item->reference_price);

        @unlink($fixturePath);
    }

    public function test_create_product_action_links_a_new_product_to_the_pricelist_row(): void
    {
        $item = PriceListItem::create([
            'company_id' => $this->company->id,
            'brand' => 'Hikvision',
            'sku' => 'CAM-100',
            'description' => 'A basic camera',
            'reference_price' => 100,
        ]);

        Livewire::test(ListPriceListItems::class)
            ->callTableAction('syncProduct', $item)
            ->assertHasNoTableActionErrors();

        $product = Product::where('price_list_item_id', $item->id)->first();

        $this->assertNotNull($product);
        $this->assertSame('CAM-100', $product->sku);
        $this->assertSame('100.0000', (string) $product->unit_cost);

        // Re-running it refreshes the same Product rather than duplicating it.
        $item->update(['reference_price' => 120]);

        Livewire::test(ListPriceListItems::class)
            ->callTableAction('syncProduct', $item)
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, Product::where('price_list_item_id', $item->id)->count());
        $this->assertSame('120.0000', (string) $product->fresh()->unit_cost);
    }
}
