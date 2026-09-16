<?php

namespace Tests\Feature\Procurement;

use App\Enums\UnitOfMeasure;
use App\Livewire\TallStackVendorBillForm;
use App\Livewire\TallStackVendorPurchaseOrderForm;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two related, forward-looking-only business rules wired onto Vendor
 * Purchase Order and Vendor Bill line items (the `quantity` column itself
 * stays decimal(15,4) — real imported legacy data may carry genuinely
 * fractional quantities, and issued/historical documents are never
 * altered): (1) a NEW/EDITED line item's quantity must be a whole number,
 * and (2) `unit` (App\Enums\UnitOfMeasure) is now a real per-line field,
 * autofilled from the selected product and shown on the admin table and
 * PDF. Mirrors the pattern already proven for Products
 * (tests/Feature/Parties/CatalogItemTest.php) and Invoice/Quotation item
 * discount coverage in this same directory.
 */
class VendorLineItemQuantityAndUnitTest extends TestCase
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

    private function makePurchaseOrder(): VendorPurchaseOrder
    {
        return VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'PO-'.uniqid(),
        ])->fresh();
    }

    private function makeBill(): VendorBill
    {
        $po = $this->makePurchaseOrder();

        return VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'VBL-'.uniqid(),
        ])->fresh();
    }

    // --- Whole-number quantity validation ---------------------------------

    public function test_vendor_purchase_order_item_rejects_a_fractional_quantity(): void
    {
        $po = $this->makePurchaseOrder();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('addItem')
            ->set('item_title', 'Cabling')
            ->set('item_quantity', 2.5)
            ->set('item_unit_cost', 100)
            ->call('saveItem')
            ->assertHasErrors(['item_quantity']);

        $this->assertSame(0, $po->items()->count());
    }

    public function test_vendor_purchase_order_item_accepts_a_whole_number_quantity(): void
    {
        $po = $this->makePurchaseOrder();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('addItem')
            ->set('item_title', 'Cabling')
            ->set('item_quantity', 5)
            ->set('item_unit_cost', 100)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame('5.0000', (string) $po->items()->sole()->quantity);
    }

    public function test_vendor_bill_item_rejects_a_fractional_quantity(): void
    {
        $bill = $this->makeBill();

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->call('addItem')
            ->set('item_title', 'Cabling')
            ->set('item_quantity', 1.5)
            ->set('item_unit_cost', 100)
            ->call('saveItem')
            ->assertHasErrors(['item_quantity']);

        $this->assertSame(0, $bill->items()->count());
    }

    public function test_vendor_bill_item_accepts_a_whole_number_quantity(): void
    {
        $bill = $this->makeBill();

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->call('addItem')
            ->set('item_title', 'Cabling')
            ->set('item_quantity', 3)
            ->set('item_unit_cost', 100)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame('3.0000', (string) $bill->items()->sole()->quantity);
    }

    // --- Unit autofill from product, persistence, and editability ---------

    public function test_vendor_purchase_order_item_unit_autofills_from_the_selected_product_and_is_saved(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Cat6 cable roll',
            'unit' => UnitOfMeasure::Roll,
            'unit_cost' => 250,
        ]);

        $po = $this->makePurchaseOrder();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('addItem')
            ->set('item_product_id', (string) $product->id)
            ->assertSet('item_unit', UnitOfMeasure::Roll->value)
            ->set('item_quantity', 2)
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = $po->items()->sole();
        $this->assertSame(UnitOfMeasure::Roll, $item->unit);
    }

    public function test_vendor_purchase_order_item_unit_stays_editable_after_the_product_autofill(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Cat6 cable roll',
            'unit' => UnitOfMeasure::Roll,
            'unit_cost' => 250,
        ]);

        $po = $this->makePurchaseOrder();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('addItem')
            ->set('item_product_id', (string) $product->id)
            ->assertSet('item_unit', UnitOfMeasure::Roll->value)
            // The autofill is a one-time default, not a lock — the line
            // may legitimately differ from the product's own catalog unit.
            ->set('item_unit', UnitOfMeasure::Box->value)
            ->set('item_quantity', 2)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(UnitOfMeasure::Box, $po->items()->sole()->unit);
    }

    public function test_vendor_bill_item_unit_autofills_from_the_selected_product_and_is_saved(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Dome camera',
            'unit' => UnitOfMeasure::Piece,
            'unit_cost' => 500,
        ]);

        $bill = $this->makeBill();

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->call('addItem')
            ->set('item_product_id', (string) $product->id)
            ->assertSet('item_unit', UnitOfMeasure::Piece->value)
            ->set('item_quantity', 4)
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = $bill->items()->sole();
        $this->assertSame(UnitOfMeasure::Piece, $item->unit);
    }

    public function test_editing_a_vendor_bill_item_loads_its_stored_unit_into_the_form(): void
    {
        $bill = $this->makeBill();

        $item = VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Dome camera',
            'quantity' => 4,
            'unit' => UnitOfMeasure::Piece,
            'unit_cost' => 500,
            'net_amount' => 2000,
            'tax_amount' => 0,
            'line_total' => 2000,
        ]);

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->call('editItem', $item->id)
            ->assertSet('item_unit', UnitOfMeasure::Piece->value);
    }

    public function test_editing_a_vendor_purchase_order_item_loads_its_stored_unit_into_the_form(): void
    {
        $po = $this->makePurchaseOrder();

        $item = VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Dome camera',
            'quantity' => 4,
            'unit' => UnitOfMeasure::Piece,
            'unit_cost' => 500,
            'line_total' => 2000,
        ]);

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('editItem', $item->id)
            ->assertSet('item_unit', UnitOfMeasure::Piece->value);
    }

    // --- Rendered line-items table shows the unit abbreviation ------------

    public function test_vendor_purchase_order_line_items_table_shows_the_unit_abbreviation(): void
    {
        $po = $this->makePurchaseOrder();

        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cat6 cable roll',
            'quantity' => 20,
            'unit' => UnitOfMeasure::Roll,
            'unit_cost' => 100,
            'line_total' => 2000,
        ]);

        $rows = Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po->fresh(['items'])])
            ->viewData('items');

        $this->assertSame('roll', collect($rows)->first()['unit']);
    }

    public function test_vendor_bill_line_items_table_shows_the_unit_abbreviation(): void
    {
        $bill = $this->makeBill();

        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Dome camera',
            'quantity' => 20,
            'unit' => UnitOfMeasure::Piece,
            'unit_cost' => 100,
            'net_amount' => 2000,
            'tax_amount' => 0,
            'line_total' => 2000,
        ]);

        $rows = Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill->fresh(['items'])])
            ->viewData('items');

        $this->assertSame('pcs', collect($rows)->first()['unit']);
    }

    // --- PDF renders the unit next to quantity -----------------------------

    public function test_vendor_purchase_order_pdf_shows_the_unit_abbreviation_next_to_quantity(): void
    {
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'ACM-VPO-UNIT-0001',
            'status' => 'draft',
            'po_date' => '2026-09-01',
            'total' => 2000,
        ]);
        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cat6 cable roll',
            'quantity' => 20,
            'unit' => UnitOfMeasure::Roll,
            'unit_cost' => 100,
            'line_total' => 2000,
        ]);

        $html = view('pdf.vendor-purchase-order', ['vendorPurchaseOrder' => $po->fresh(['items', 'vendor', 'company'])])->render();

        $this->assertStringContainsString('20 roll', $html);
    }

    public function test_vendor_bill_pdf_shows_the_unit_abbreviation_next_to_quantity(): void
    {
        $bill = $this->makeBill();
        $bill->update(['total' => 2000]);
        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Dome camera',
            'quantity' => 20,
            'unit' => UnitOfMeasure::Piece,
            'unit_cost' => 100,
            'net_amount' => 2000,
            'tax_amount' => 0,
            'line_total' => 2000,
        ]);

        $html = view('pdf.vendor-bill', ['vendorBill' => $bill->fresh(['items', 'vendor', 'company'])])->render();

        $this->assertStringContainsString('20 pcs', $html);
    }
}
