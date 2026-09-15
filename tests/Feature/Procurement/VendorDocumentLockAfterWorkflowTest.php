<?php

namespace Tests\Feature\Procurement;

use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\SubmitVendorBill;
use App\Enums\VendorBillStatus;
use App\Filament\Resources\VendorBills\Pages\ListVendorBills;
use App\Filament\Resources\VendorBills\Pages\ViewVendorBill;
use App\Filament\Resources\VendorBills\RelationManagers\ItemsRelationManager as VendorBillItemsRelationManager;
use App\Filament\Resources\VendorPurchaseOrders\Pages\ListVendorPurchaseOrders;
use App\Filament\Resources\VendorPurchaseOrders\Pages\ViewVendorPurchaseOrder;
use App\Filament\Resources\VendorPurchaseOrders\RelationManagers\ItemsRelationManager as VendorPoItemsRelationManager;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two Codex review findings on PR #4:
 * 1. Both Vendor Bill and Vendor PO ItemsRelationManagers exposed
 *    unconditional edit/delete/reorder even after the owner document left
 *    Draft, silently mutating already-approved payable evidence.
 * 2. Both tables' ForceDeleteBulkAction bypassed status entirely once the
 *    Trashed filter was set, physically erasing referenced documents.
 */
class VendorDocumentLockAfterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    public function test_vendor_po_items_cannot_be_edited_once_approved(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0001']);
        $item = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'line_total' => 1000]);
        $po->forceFill(['total' => 1000])->save();
        app(ApproveVendorPurchaseOrder::class)->approve($po->fresh());

        Livewire::test(VendorPoItemsRelationManager::class, [
            'ownerRecord' => $po->fresh(),
            'pageClass' => ViewVendorPurchaseOrder::class,
        ])
            ->assertTableActionHidden('edit', $item)
            ->assertTableActionHidden('delete', $item);
    }

    public function test_vendor_po_items_can_be_edited_while_draft(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0002']);
        $item = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'line_total' => 1000]);

        Livewire::test(VendorPoItemsRelationManager::class, [
            'ownerRecord' => $po->fresh(),
            'pageClass' => ViewVendorPurchaseOrder::class,
        ])
            ->assertTableActionVisible('edit', $item)
            ->assertTableActionVisible('delete', $item);
    }

    public function test_vendor_bill_items_cannot_be_edited_once_submitted(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0003']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0001']);
        $item = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'net_amount' => 1000, 'tax_amount' => 0, 'line_total' => 1000]);
        $bill->forceFill(['total' => 1000])->save();
        app(SubmitVendorBill::class)->submit($bill->fresh());

        Livewire::test(VendorBillItemsRelationManager::class, [
            'ownerRecord' => $bill->fresh(),
            'pageClass' => ViewVendorBill::class,
        ])
            ->assertTableActionHidden('edit', $item)
            ->assertTableActionHidden('delete', $item);
    }

    public function test_vendor_bill_items_can_be_edited_while_draft(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0004']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0002']);
        $item = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'net_amount' => 1000, 'tax_amount' => 0, 'line_total' => 1000]);

        Livewire::test(VendorBillItemsRelationManager::class, [
            'ownerRecord' => $bill->fresh(),
            'pageClass' => ViewVendorBill::class,
        ])
            ->assertTableActionVisible('edit', $item)
            ->assertTableActionVisible('delete', $item);
    }

    public function test_force_delete_skips_an_approved_vendor_purchase_order_with_a_bill(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0005']);
        $po->forceFill(['total' => 1000])->save();
        app(ApproveVendorPurchaseOrder::class)->approve($po->fresh());
        VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0003']);
        $po->delete(); // soft delete first — force-delete only applies to a selectable trashed row.

        Livewire::test(ListVendorPurchaseOrders::class)
            ->filterTable('trashed')
            ->callTableBulkAction('forceDelete', [$po]);

        $this->assertDatabaseHas('vendor_purchase_orders', ['id' => $po->id]);
    }

    public function test_force_delete_removes_an_unreferenced_draft_vendor_purchase_order(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0006']);
        $po->delete();

        Livewire::test(ListVendorPurchaseOrders::class)
            ->filterTable('trashed')
            ->callTableBulkAction('forceDelete', [$po]);

        $this->assertDatabaseMissing('vendor_purchase_orders', ['id' => $po->id]);
    }

    public function test_force_delete_skips_a_submitted_vendor_bill(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0007']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0004']);
        $bill->forceFill(['total' => 1000, 'status' => VendorBillStatus::Submitted])->save();
        $bill->delete();

        Livewire::test(ListVendorBills::class)
            ->filterTable('trashed')
            ->callTableBulkAction('forceDelete', [$bill]);

        $this->assertDatabaseHas('vendor_bills', ['id' => $bill->id]);
    }

    public function test_force_delete_removes_an_unreferenced_draft_vendor_bill(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0008']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0005']);
        $bill->delete();

        Livewire::test(ListVendorBills::class)
            ->filterTable('trashed')
            ->callTableBulkAction('forceDelete', [$bill]);

        $this->assertDatabaseMissing('vendor_bills', ['id' => $bill->id]);
    }
}
