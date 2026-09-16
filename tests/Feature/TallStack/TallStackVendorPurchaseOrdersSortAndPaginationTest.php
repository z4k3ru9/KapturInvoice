<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackVendorPurchaseOrders;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers wiring the TallStackUI <x-table>'s built-in sort/quantity onto
 * this register: sorting by the joined "vendor" column (vendors.name,
 * joined once on the paginated query only — see render()'s own withSum()
 * neighbor, untouched by this join) must not throw an ambiguous-column
 * SQL error, and the quantity filter must actually cap the page size.
 */
class TallStackVendorPurchaseOrdersSortAndPaginationTest extends TestCase
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

    private function poForVendor(string $vendorName): VendorPurchaseOrder
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => $vendorName]);

        return VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'number' => 'PO-'.uniqid(),
            'status' => 'draft',
            'po_date' => now(),
            'total' => 1000,
        ]);
    }

    public function test_sorting_by_vendor_orders_by_the_joined_name_and_quantity_caps_the_page(): void
    {
        $this->poForVendor('Zeta Vendor');
        $this->poForVendor('Alpha Vendor');
        $this->poForVendor('Mid Vendor');

        $component = Livewire::test(TallStackVendorPurchaseOrders::class, ['company' => $this->company])
            ->set('sort', ['column' => 'vendor', 'direction' => 'asc'])
            ->assertHasNoErrors();

        $names = collect($component->viewData('purchaseOrders')->items())->pluck('vendor')->all();
        $this->assertSame(['Alpha Vendor', 'Mid Vendor', 'Zeta Vendor'], $names);

        $limited = Livewire::test(TallStackVendorPurchaseOrders::class, ['company' => $this->company])
            ->set('quantity', 2)
            ->assertHasNoErrors();

        $this->assertCount(2, $limited->viewData('purchaseOrders')->items());
        $this->assertSame(3, $limited->viewData('purchaseOrders')->total());
    }
}
