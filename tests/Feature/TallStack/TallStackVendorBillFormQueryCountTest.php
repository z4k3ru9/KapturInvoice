<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackVendorBillForm;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for a real N+1 found in this component's render():
 * every line item called $item->unallocatedAmount() twice
 * ('unallocated'/'unallocated_raw'), and that model method always runs its
 * own $this->jobCostAllocations()->sum('amount') query rather than using
 * the 'items.jobCostAllocations' relation already eager-loaded in
 * mount()/refreshBill() — so render() issued two extra queries per item
 * regardless of allocation count. render() now sums each item's
 * already-loaded jobCostAllocations collection instead of calling the
 * model method (which stays untouched — App\Actions\Procurement\
 * AllocateJobCost still needs it to read a fresh, uncached total for its
 * over-allocation guard).
 */
class TallStackVendorBillFormQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor Co']);
        $this->actingAs($this->user);
    }

    private function makeBill(int $itemCount): VendorBill
    {
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'PO-'.uniqid(),
        ]);

        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'VBL-'.uniqid(),
        ]);

        for ($i = 0; $i < $itemCount; $i++) {
            VendorBillItem::create([
                'vendor_bill_id' => $bill->id,
                'title' => "Item {$i}",
                'quantity' => 1,
                'unit_cost' => 100,
                'net_amount' => 100,
                'tax_amount' => 0,
                'line_total' => 100,
            ]);
        }

        return $bill->fresh();
    }

    private function queryCountForRender(VendorBill $bill): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $bill,
        ]);

        DB::flushQueryLog();

        return $count;
    }

    public function test_query_count_stays_flat_as_item_count_grows(): void
    {
        $small = $this->queryCountForRender($this->makeBill(2));
        $large = $this->queryCountForRender($this->makeBill(10));

        $this->assertSame($small, $large, 'A vendor bill detail page must not issue more queries as its own line count grows.');
    }

    public function test_displayed_unallocated_amount_matches_the_models_own_computation(): void
    {
        $bill = $this->makeBill(1);
        $item = $bill->items->first();

        $component = Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $bill,
        ]);

        $row = collect($component->viewData('items'))->firstWhere('id', $item->id);

        $this->assertSame($item->unallocatedAmount(), $row['unallocated_raw']);
    }
}
