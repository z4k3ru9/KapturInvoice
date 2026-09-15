<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackVendorPurchaseOrders;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPoVariance;
use App\Models\VendorPurchaseOrder;
use App\Support\Dashboard\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for a real N+1 found in this component: its list
 * called $po->paymentCeiling() per row, which runs its own
 * variances()->sum('amount') query each time. render() now eager-loads
 * ->withSum('variances', 'amount') and derives the same value from it.
 */
class TallStackVendorPurchaseOrdersQueryCountTest extends TestCase
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

    private function makePurchaseOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $po = VendorPurchaseOrder::create([
                'company_id' => $this->company->id,
                'vendor_id' => $this->vendor->id,
                'number' => 'PO-'.uniqid(),
                'status' => 'approved',
                'po_date' => now(),
                'total' => 1000,
            ]);

            VendorPoVariance::create([
                'vendor_purchase_order_id' => $po->id,
                'reason' => 'Test variance',
                'amount' => 50,
                'approved_at' => now(),
            ]);
        }
    }

    private function queryCountForRender(): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        Livewire::test(TallStackVendorPurchaseOrders::class, ['company' => $this->company]);

        DB::flushQueryLog();

        return $count;
    }

    public function test_query_count_stays_flat_as_purchase_order_count_grows(): void
    {
        $this->makePurchaseOrders(2);
        $small = $this->queryCountForRender();

        $this->makePurchaseOrders(8);
        $large = $this->queryCountForRender();

        $this->assertSame($small, $large, 'A page of purchase orders must not issue more queries as the row count within the page grows.');
    }

    public function test_payment_ceiling_matches_the_models_own_computation(): void
    {
        $this->makePurchaseOrders(1);
        $po = VendorPurchaseOrder::first();

        $component = Livewire::test(TallStackVendorPurchaseOrders::class, ['company' => $this->company]);
        $row = collect($component->viewData('purchaseOrders')->items())->firstWhere('id', $po->id);

        $this->assertSame(
            Money::format($po->paymentCeiling(), $this->company->currency_code),
            $row['payment_ceiling'],
        );
    }
}
