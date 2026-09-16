<?php

namespace Tests\Feature\Parties;

use App\Enums\CatalogItemType;
use App\Enums\TaxCategory;
use App\Enums\UnitOfMeasure;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 02 (docs/rebuild/specs/02-parties-and-catalog/Specs.md): `Product`
 * now models any catalog item a quotation/invoice line can pull from —
 * product, service, labor, or other — not just physical goods. See
 * App\Models\Product's docblock for why this is an extension of the
 * existing model/table rather than a new `catalog_items` one.
 */
class CatalogItemTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
    }

    public function test_all_four_catalog_item_types_are_selectable(): void
    {
        foreach (CatalogItemType::cases() as $type) {
            $item = Product::create([
                'company_id' => $this->company->id,
                'name' => "Item ({$type->value})",
                'type' => $type,
            ]);

            $this->assertSame($type, $item->fresh()->type);
        }
    }

    public function test_type_defaults_to_product(): void
    {
        $item = Product::create(['company_id' => $this->company->id, 'name' => 'Untyped item']);

        $this->assertSame(CatalogItemType::Product, $item->fresh()->type);
    }

    public function test_tax_category_defaults_to_standard_taxable_and_can_be_set_non_taxable(): void
    {
        $default = Product::create(['company_id' => $this->company->id, 'name' => 'Default tax item']);
        $this->assertSame(TaxCategory::StandardTaxable, $default->fresh()->tax_category);

        $nonTaxable = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Non-taxable item',
            'tax_category' => TaxCategory::NonTaxable,
        ]);
        $this->assertSame(TaxCategory::NonTaxable, $nonTaxable->fresh()->tax_category);
    }

    public function test_stock_flag_is_a_plain_boolean_label_only(): void
    {
        $item = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Stocked item',
            'stock_flag' => true,
        ]);

        $this->assertTrue($item->fresh()->stock_flag);
        // Nothing on the model computes availability from it — it's a
        // display label only (see the migration's docblock). There is no
        // "quantity on hand"/"available" attribute to assert absent-of;
        // this test exists so a future PR that tries to wire real
        // inventory logic to this flag has to touch this test, not add
        // silently around it.
        $this->assertFalse(method_exists(Product::class, 'isAvailable'));
        $this->assertFalse(method_exists(Product::class, 'quantityOnHand'));
    }

    public function test_unit_and_default_price_are_stored_for_service_and_labor_lines(): void
    {
        $service = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Installation service',
            'type' => CatalogItemType::Service,
            'unit' => 'hour',
            'unit_cost' => 150,
        ]);

        $this->assertSame(CatalogItemType::Service, $service->fresh()->type);
        $this->assertSame(UnitOfMeasure::Hour, $service->fresh()->unit);
        $this->assertSame('150.0000', $service->fresh()->unit_cost);
    }
}
