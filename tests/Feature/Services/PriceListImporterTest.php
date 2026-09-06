<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\PriceListItem;
use App\Services\PriceListImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Exercises the parser against a small synthetic workbook mirroring the
 * real vendor pricelists this was built against (see
 * docs/price-list-import.md): a sheet stacking two product families end to
 * end, each with its own header row, one interrupted by a one-cell
 * sub-category label — plus a sheet with no recognizable pricelist table
 * at all, which should be skipped rather than misread.
 */
class PriceListImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = tempnam(sys_get_temp_dir(), 'pricelist').'.xlsx';
        $this->buildFixture($this->fixturePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixturePath);

        parent::tearDown();
    }

    private function buildFixture(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        // Sheet 1: a camera family, then a sub-category label, then an NVR
        // family with a completely different set of spec columns — only
        // Model/Description/price columns are shared, exactly like the
        // real dealer pricelists.
        $writer->addRow(Row::fromValues(['Appearance', 'Model', 'Resolution', 'Description', 'Dealer price', 'online price', 'MSRP']));
        $writer->addRow(Row::fromValues([null, 'CAM-100', '2MP', 'A basic camera', 100.0, 120.0, 150.0]));
        $writer->addRow(Row::fromValues([null, 'CAM-200', '4MP', 'A better camera', 200.0, 240.0, 300.0]));
        $writer->addRow(Row::fromValues(['2MP Eco Series with a hybrid light']));
        $writer->addRow(Row::fromValues([null, 'CAM-300', '2MP', 'An eco camera', 90.0, 108.0, 135.0]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Appearance', 'Basic Model', 'Input Bandwidth', 'Description', 'Dealer price', 'MSRP']));
        $writer->addRow(Row::fromValues([null, 'NVR-100', '40Mbps', 'A basic NVR', 500.0, 700.0]));

        $writer->addNewSheetAndMakeItCurrent();
        // Sheet 2: no Model/Description/price columns at all — should be
        // recognized as not a pricelist table and skipped, not misread.
        $writer->addRow(Row::fromValues(['Date', 'Notes']));
        $writer->addRow(Row::fromValues(['2024-01-01', 'Some changelog entry, not a price table']));

        $writer->close();
    }

    public function test_imports_stacked_product_families_with_category_tracking_and_reference_price(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        $stats = app(PriceListImporter::class)->import($this->fixturePath, $company, 'TestBrand', 'fixture.xlsx');

        $this->assertSame(4, $stats['rows_read']);
        $this->assertSame(4, $stats['created']);
        $this->assertSame(0, $stats['updated']);
        $this->assertContains('Sheet2', $stats['sheets_skipped']);
        $this->assertNotContains('Sheet1', $stats['sheets_skipped']);

        $this->assertSame(4, PriceListItem::where('company_id', $company->id)->count());

        $cam100 = PriceListItem::where('sku', 'CAM-100')->first();
        $this->assertSame('Sheet1', $cam100->category); // default: sheet name, before any label row
        $this->assertSame('100.0000', $cam100->reference_price); // "Dealer price" preferred over MSRP
        $this->assertEquals(['Dealer price' => 100.0, 'online price' => 120.0, 'MSRP' => 150.0], $cam100->prices);

        $cam300 = PriceListItem::where('sku', 'CAM-300')->first();
        $this->assertSame('2MP Eco Series with a hybrid light', $cam300->category);

        $nvr100 = PriceListItem::where('sku', 'NVR-100')->first();
        $this->assertSame('Sheet1', $nvr100->category); // a fresh header row resets category back to the sheet name
        $this->assertSame('500.0000', $nvr100->reference_price);

        // Re-importing the same file updates the existing rows rather than duplicating them.
        $stats = app(PriceListImporter::class)->import($this->fixturePath, $company, 'TestBrand', 'fixture.xlsx');
        $this->assertSame(0, $stats['created']);
        $this->assertSame(4, $stats['updated']);
        $this->assertSame(4, PriceListItem::where('company_id', $company->id)->count());
    }
}
