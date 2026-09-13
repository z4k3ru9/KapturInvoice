<?php

namespace Tests\Feature\Filament;

use App\Enums\VendorBillStatus;
use App\Enums\VendorPurchaseOrderStatus;
use App\Filament\Resources\VendorBills\Pages\ListVendorBills;
use App\Filament\Resources\VendorBills\Pages\ViewVendorBill;
use App\Filament\Resources\VendorBills\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\VendorPurchaseOrders\Pages\ListVendorPurchaseOrders;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the Phase 05 (docs/rebuild/specs/05-procurement-and-delivery)
 * Approve/Submit/Approve/Record payment/Allocate-to-job table actions
 * wired onto the new VendorPurchaseOrder/VendorBill Filament resources —
 * the underlying Action classes (App\Actions\Procurement\*) are unit
 * tested elsewhere; this file proves the Livewire wiring itself actually
 * calls them.
 */
class ProcurementActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    private function draftPurchaseOrder(float $total = 1000): VendorPurchaseOrder
    {
        return VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'ACME-VPO-'.uniqid(),
            'status' => VendorPurchaseOrderStatus::Draft,
            'total' => $total,
        ]);
    }

    private function approvedPurchaseOrder(float $total = 1000): VendorPurchaseOrder
    {
        $po = $this->draftPurchaseOrder($total);
        $po->forceFill(['status' => VendorPurchaseOrderStatus::Approved, 'approved_at' => now()])->save();

        return $po;
    }

    private function draftBill(VendorPurchaseOrder $po, float $total = 1000): VendorBill
    {
        return VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'ACME-VBL-'.uniqid(),
            'status' => VendorBillStatus::Draft,
            'total' => $total,
        ]);
    }

    public function test_the_approve_table_action_approves_a_draft_vendor_purchase_order(): void
    {
        $po = $this->draftPurchaseOrder();

        Livewire::test(ListVendorPurchaseOrders::class)
            ->callTableAction('approve', $po);

        $this->assertSame(VendorPurchaseOrderStatus::Approved, $po->fresh()->status);
        $this->assertNotNull($po->fresh()->approved_at);
    }

    public function test_the_submit_table_action_submits_a_draft_vendor_bill(): void
    {
        $po = $this->approvedPurchaseOrder();
        $bill = $this->draftBill($po);

        Livewire::test(ListVendorBills::class)
            ->callTableAction('submit', $bill);

        $this->assertSame(VendorBillStatus::Submitted, $bill->fresh()->status);
    }

    public function test_the_approve_table_action_approves_a_submitted_vendor_bill(): void
    {
        $po = $this->approvedPurchaseOrder();
        $bill = $this->draftBill($po);
        $bill->forceFill(['status' => VendorBillStatus::Submitted])->save();

        Livewire::test(ListVendorBills::class)
            ->callTableAction('approve', $bill);

        $this->assertSame(VendorBillStatus::Approved, $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->approved_at);
    }

    public function test_the_record_payment_table_action_records_a_vendor_payment(): void
    {
        $po = $this->approvedPurchaseOrder(1000);
        $bill = $this->draftBill($po, 1000);
        $bill->forceFill(['status' => VendorBillStatus::Approved])->save();

        Livewire::test(ListVendorBills::class)
            ->callTableAction('recordPayment', $bill, data: [
                'amount' => 400,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
                'reference' => 'TRX-001',
            ]);

        $fresh = $bill->fresh();
        $this->assertSame(400.0, (float) $fresh->amount_paid);
        $this->assertSame(600.0, (float) $fresh->balance);
        $this->assertSame(VendorBillStatus::PartiallyPaid, $fresh->status);
        $this->assertSame(1, $fresh->payments()->count());
    }

    public function test_the_allocate_to_job_relation_manager_table_action_allocates_cost_to_a_sales_order(): void
    {
        $po = $this->approvedPurchaseOrder(1000);
        $bill = $this->draftBill($po, 1000);
        $item = VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling',
            'quantity' => 1,
            'unit_cost' => 1000,
            'net_amount' => 1000,
            'tax_amount' => 0,
            'line_total' => 1000,
        ]);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACME-QUO-0001',
            'status' => 'draft',
        ]);
        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACME-SO-0001',
            'status' => 'draft',
            'approved_value' => 0,
        ]);

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $bill, 'pageClass' => ViewVendorBill::class])
            ->callTableAction('allocateToJob', $item, data: [
                'sales_order_id' => $salesOrder->id,
                'amount' => 600,
            ]);

        $this->assertSame(1, $item->fresh()->jobCostAllocations()->count());
        $this->assertSame(400.0, $item->fresh()->unallocatedAmount());
    }
}
