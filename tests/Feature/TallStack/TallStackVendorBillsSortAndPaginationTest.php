<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackVendorBills;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers wiring the vendor TallStackUI <x-table>'s built-in sort/quantity
 * onto this register: sorting by the joined "vendor" column (vendor_bills
 * has no vendor name of its own — vendors.name is joined once on the
 * paginated query only, per render()'s own docblock-free but explicit
 * join) must not throw an ambiguous-column SQL error, and the quantity
 * filter (a plain public $quantity property feeding ->paginate()) must
 * actually cap the page size.
 */
class TallStackVendorBillsSortAndPaginationTest extends TestCase
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

    private function billForVendor(string $vendorName): VendorBill
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => $vendorName]);
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'number' => 'PO-'.uniqid(),
            'status' => 'approved',
            'po_date' => now(),
            'total' => 1000,
        ]);

        return VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'BILL-'.uniqid(),
            'status' => 'draft',
            'bill_date' => now(),
            'total' => 1000,
        ]);
    }

    public function test_sorting_by_vendor_orders_by_the_joined_name_and_quantity_caps_the_page(): void
    {
        // Created out of alphabetical order on purpose, so a correct join
        // + orderBy('vendors.name', ...) is the only way this passes.
        $this->billForVendor('Zeta Vendor');
        $this->billForVendor('Alpha Vendor');
        $this->billForVendor('Mid Vendor');

        $component = Livewire::test(TallStackVendorBills::class, ['company' => $this->company])
            ->set('sort', ['column' => 'vendor', 'direction' => 'asc'])
            ->assertHasNoErrors();

        $names = collect($component->viewData('bills')->items())->pluck('vendor')->all();
        $this->assertSame(['Alpha Vendor', 'Mid Vendor', 'Zeta Vendor'], $names);

        // Page-size selector: 3 rows exist, quantity=2 must cap the page.
        $limited = Livewire::test(TallStackVendorBills::class, ['company' => $this->company])
            ->set('quantity', 2)
            ->assertHasNoErrors();

        $this->assertCount(2, $limited->viewData('bills')->items());
        $this->assertSame(3, $limited->viewData('bills')->total());
    }
}
