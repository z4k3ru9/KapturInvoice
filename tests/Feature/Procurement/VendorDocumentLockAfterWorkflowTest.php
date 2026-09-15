<?php

namespace Tests\Feature\Procurement;

use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\SubmitVendorBill;
use App\Livewire\TallStackVendorBillForm;
use App\Livewire\TallStackVendorPurchaseOrderForm;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Codex review finding on PR #4: both Vendor Bill and Vendor PO
 * ItemsRelationManagers exposed unconditional edit/delete/reorder even
 * after the owner document left Draft, silently mutating already-approved
 * payable evidence.
 *
 * Ported from the Filament relation-manager table-action-visibility tests
 * during the Filament-removal Phase B — the same Draft-only guard now
 * lives inline on App\Livewire\TallStackVendorPurchaseOrderForm::
 * saveItem()/deleteItem() and TallStackVendorBillForm::saveItem()/
 * deleteItem() (both silently no-op once the owner document has left
 * Draft), so this proves the guard through those methods instead.
 *
 * The Filament-only force-delete-bulk-action tests this file used to
 * carry were later rebuilt as a real TallStack row action
 * (`App\Actions\Procurement\ForceDeleteVendorBill`/
 * `ForceDeleteVendorPurchaseOrder`) and their coverage now lives in
 * `Tests\Feature\Authorization\ForceDeleteGuardsTest`, not here.
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
        app(Tenancy::class)->set($this->company);
    }

    public function test_vendor_po_items_cannot_be_edited_once_approved(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0001']);
        $item = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'line_total' => 1000]);
        $po->forceFill(['total' => 1000])->save();
        app(ApproveVendorPurchaseOrder::class)->approve($po->fresh());

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po->fresh()])
            ->set('item_title', 'Changed title')
            ->call('editItem', $item->id)
            ->call('saveItem')
            ->call('deleteItem', $item->id);

        $this->assertDatabaseHas('vendor_purchase_order_items', ['id' => $item->id, 'title' => 'Cabling']);
    }

    public function test_vendor_po_items_can_be_edited_while_draft(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0002']);
        $item = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'line_total' => 1000]);

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po->fresh()])
            ->call('editItem', $item->id)
            ->set('item_title', 'Changed title')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 1000)
            ->call('saveItem');

        $this->assertDatabaseHas('vendor_purchase_order_items', ['id' => $item->id, 'title' => 'Changed title']);
    }

    public function test_vendor_bill_items_cannot_be_edited_once_submitted(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0003']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0001']);
        $item = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'net_amount' => 1000, 'tax_amount' => 0, 'line_total' => 1000]);
        $bill->forceFill(['total' => 1000])->save();
        app(SubmitVendorBill::class)->submit($bill->fresh());

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill->fresh()])
            ->call('editItem', $item->id)
            ->set('item_title', 'Changed title')
            ->call('saveItem')
            ->call('deleteItem', $item->id);

        $this->assertDatabaseHas('vendor_bill_items', ['id' => $item->id, 'title' => 'Cabling']);
    }

    public function test_vendor_bill_items_can_be_edited_while_draft(): void
    {
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'number' => 'ACM-VPO-0004']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0002']);
        $item = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 1000, 'net_amount' => 1000, 'tax_amount' => 0, 'line_total' => 1000]);

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill->fresh()])
            ->call('editItem', $item->id)
            ->set('item_title', 'Changed title')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 1000)
            ->set('item_net_amount', 1000)
            ->set('item_tax_amount', 0)
            ->call('saveItem');

        $this->assertDatabaseHas('vendor_bill_items', ['id' => $item->id, 'title' => 'Changed title']);
    }
}
