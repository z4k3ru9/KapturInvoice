<?php

namespace Tests\Feature\Documents;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPurchaseOrderStatus;
use App\Livewire\TallStackInvoiceForm;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackVendorBillForm;
use App\Livewire\TallStackVendorPurchaseOrderForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
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
 * Rebuilds the drag/keyboard reorder coverage that lived on
 * `tests/Feature/Filament/DynamicRowReorderTest.php` before the Filament
 * removal, against the plain TALL-stack forms' own `reorderItems()`
 * method (App\Livewire\Tallstack{Invoice,Quotation,VendorBill,
 * VendorPurchaseOrder}Form) — a clean 1:1 port of the persistence
 * contract, even though the UI trigger mechanism (native HTML5 drag and
 * drop / keyboard move buttons, see resources/views/components/tallstack/
 * reorderable-items-table.blade.php) is entirely different from
 * Filament's own `reorderTable()` table action.
 *
 * Rule (Codex review finding on PR #4, restated in each Form's own
 * docblock): reordering is a no-op — never even attempted — once the
 * owning document has left its own Draft-equivalent status, since
 * reordering an already-issued/submitted/approved document's items would
 * silently mutate already-printed evidence and break the positional
 * correspondence with any frozen snapshot's line-by-line breakdown.
 */
class DynamicRowReorderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);
    }

    // --- Invoice ----------------------------------------------------------

    public function test_reordering_invoice_items_persists_sort_order_in_one_livewire_call(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-REORDER',
            'currency_code' => 'USD',
            'discount_is_percentage' => false,
        ]);

        $first = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);
        $third = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Third typed', 'quantity' => 1, 'unit_cost' => 300, 'sort_order' => 2]);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $invoice->fresh()->items->pluck('id')->all(),
        );

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('reorderItems', [$third->id, $first->id, $second->id]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $invoice->fresh()->items->pluck('id')->all(),
        );
    }

    public function test_reordering_an_issued_invoices_items_is_rejected(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Issued,
            'number' => 'ACM-INV-ISSUED-REORDER',
            'currency_code' => 'USD',
            'discount_is_percentage' => false,
        ]);

        $first = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('reorderItems', [$second->id, $first->id]);

        $this->assertSame(
            [$first->id, $second->id],
            $invoice->fresh()->items->pluck('id')->all(),
            'reorder must be a no-op once the invoice is Issued',
        );
    }

    // --- Quotation ----------------------------------------------------------

    public function test_reordering_quotation_items_persists_sort_order_in_one_livewire_call(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'status' => QuotationStatus::Draft,
            'number' => 'ACM-QUO-REORDER',
            'pricing_mode' => 'exclusive',
            'job_type' => 'installation',
            'discount_is_percentage' => false,
        ]);

        $first = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);
        $third = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Third typed', 'quantity' => 1, 'unit_cost' => 300, 'sort_order' => 2]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('reorderItems', [$third->id, $first->id, $second->id]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $quotation->fresh()->items->pluck('id')->all(),
        );
    }

    public function test_reordering_an_approved_quotations_items_is_rejected(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'status' => QuotationStatus::Approved,
            'number' => 'ACM-QUO-APPROVED-REORDER',
            'pricing_mode' => 'exclusive',
            'job_type' => 'installation',
            'discount_is_percentage' => false,
        ]);

        $first = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('reorderItems', [$second->id, $first->id]);

        $this->assertSame(
            [$first->id, $second->id],
            $quotation->fresh()->items->pluck('id')->all(),
            'reorder must be a no-op once the quotation has left Draft',
        );
    }

    // --- Vendor bill ----------------------------------------------------------

    public function test_reordering_vendor_bill_items_persists_sort_order_in_one_livewire_call(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-VPO-REORDER']);

        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'status' => VendorBillStatus::Draft,
            'number' => 'ACM-VBL-REORDER',
        ]);

        $first = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'net_amount' => 100, 'tax_amount' => 0, 'sort_order' => 0]);
        $second = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'net_amount' => 200, 'tax_amount' => 0, 'sort_order' => 1]);
        $third = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Third typed', 'quantity' => 1, 'unit_cost' => 300, 'net_amount' => 300, 'tax_amount' => 0, 'sort_order' => 2]);

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->call('reorderItems', [$third->id, $first->id, $second->id]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $bill->fresh()->items->pluck('id')->all(),
        );
    }

    public function test_reordering_a_submitted_vendor_bills_items_is_rejected(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-VPO-REORDER-2']);

        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'status' => VendorBillStatus::Submitted,
            'number' => 'ACM-VBL-SUBMITTED-REORDER',
        ]);

        $first = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'net_amount' => 100, 'tax_amount' => 0, 'sort_order' => 0]);
        $second = VendorBillItem::create(['vendor_bill_id' => $bill->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'net_amount' => 200, 'tax_amount' => 0, 'sort_order' => 1]);

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->call('reorderItems', [$second->id, $first->id]);

        $this->assertSame(
            [$first->id, $second->id],
            $bill->fresh()->items->pluck('id')->all(),
            'reorder must be a no-op once the vendor bill has left Draft',
        );
    }

    // --- Vendor purchase order ----------------------------------------------------------

    public function test_reordering_vendor_purchase_order_items_persists_sort_order_in_one_livewire_call(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);

        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'status' => VendorPurchaseOrderStatus::Draft,
            'number' => 'ACM-VPO-DRAFT-REORDER',
        ]);

        $first = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);
        $third = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Third typed', 'quantity' => 1, 'unit_cost' => 300, 'sort_order' => 2]);

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('reorderItems', [$third->id, $first->id, $second->id]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $po->fresh()->items->pluck('id')->all(),
        );
    }

    public function test_reordering_an_approved_vendor_purchase_orders_items_is_rejected(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);

        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'status' => VendorPurchaseOrderStatus::Approved,
            'number' => 'ACM-VPO-APPROVED-REORDER',
        ]);

        $first = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = VendorPurchaseOrderItem::create(['vendor_purchase_order_id' => $po->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->call('reorderItems', [$second->id, $first->id]);

        $this->assertSame(
            [$first->id, $second->id],
            $po->fresh()->items->pluck('id')->all(),
            'reorder must be a no-op once the vendor purchase order has left Draft',
        );
    }
}
