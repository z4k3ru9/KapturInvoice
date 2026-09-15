<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPurchaseOrderStatus;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackRecurringInvoiceForm;
use App\Livewire\TallStackVendorBillForm;
use App\Livewire\TallStackVendorPurchaseOrderForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPurchaseOrder;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 13 repair plan item 2 — App\Livewire\Concerns\AutosavesDraft
 * (already covered end-to-end, including its conflict/failure/role-gating
 * machinery, by TallStackInvoiceAutosaveTest against the reference
 * implementation) rolled out to four more forms: TallStackQuotationForm,
 * TallStackRecurringInvoiceForm, TallStackVendorBillForm,
 * TallStackVendorPurchaseOrderForm. This file covers what's genuinely new
 * per form — its own autosaveModel()/autosaveFields()/autosaveGuard()
 * wiring and field whitelist — rather than re-proving the trait's already-
 * tested generic algorithm four more times.
 */
class TallStackDraftAutosaveRolloutTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);

        return $user;
    }

    // --- Quotation ------------------------------------------------------

    private function draftQuotation(): Quotation
    {
        return Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status' => QuotationStatus::Draft,
            'number' => 'ACM-QUO-AUTOSAVE',
            'pricing_mode' => 'exclusive',
            'job_type' => 'installation',
            'discount_is_percentage' => false,
        ]);
    }

    public function test_quotation_autosave_persists_terms_and_bumps_version(): void
    {
        $this->actingAsRole('owner');
        $quotation = $this->draftQuotation();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->set('terms', 'Valid for 30 days')
            ->assertSet('autosaveStatus', 'saved');

        $fresh = $quotation->fresh();
        $this->assertSame('Valid for 30 days', $fresh->terms);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_quotation_autosave_never_runs_once_it_leaves_draft(): void
    {
        $this->actingAsRole('owner');
        $quotation = $this->draftQuotation();
        $quotation->forceFill(['status' => QuotationStatus::Approved])->save();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->set('notes', 'Trying to edit an approved quotation')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertNotSame('Trying to edit an approved quotation', $quotation->fresh()->notes);
    }

    public function test_quotation_autosave_never_runs_for_a_read_only_role(): void
    {
        $this->actingAsRole('auditor');
        $quotation = $this->draftQuotation();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->set('terms', 'An auditor should not be able to save this')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertSame(0, $quotation->fresh()->draft_version);
    }

    // --- Recurring invoice ------------------------------------------------

    private function draftRecurringInvoice(): Invoice
    {
        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-RECURRING-AUTOSAVE',
            'currency_code' => 'USD',
            'discount_is_percentage' => false,
            'is_recurring' => true,
        ]);
    }

    public function test_recurring_invoice_autosave_persists_po_number_and_bumps_version(): void
    {
        $this->actingAsRole('owner');
        $invoice = $this->draftRecurringInvoice();

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('po_number', 'PO-RECUR-1')
            ->assertSet('autosaveStatus', 'saved');

        $fresh = $invoice->fresh();
        $this->assertSame('PO-RECUR-1', $fresh->po_number);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_recurring_invoice_autosave_never_runs_for_a_read_only_role(): void
    {
        $this->actingAsRole('auditor');
        $invoice = $this->draftRecurringInvoice();

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->set('footer', 'An auditor should not be able to save this')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertSame(0, $invoice->fresh()->draft_version);
    }

    // --- Vendor bill --------------------------------------------------------

    private function draftVendorBill(): VendorBill
    {
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'status' => VendorPurchaseOrderStatus::Approved,
            'number' => 'ACM-VPO-AUTOSAVE',
        ]);

        return VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'status' => VendorBillStatus::Draft,
            'number' => 'ACM-VBL-AUTOSAVE',
        ]);
    }

    public function test_vendor_bill_autosave_persists_notes_and_bumps_version(): void
    {
        $this->actingAsRole('owner');
        $bill = $this->draftVendorBill();

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->set('notes', 'Awaiting vendor confirmation')
            ->assertSet('autosaveStatus', 'saved');

        $fresh = $bill->fresh();
        $this->assertSame('Awaiting vendor confirmation', $fresh->notes);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_vendor_bill_autosave_never_runs_once_it_leaves_draft(): void
    {
        $this->actingAsRole('owner');
        $bill = $this->draftVendorBill();
        $bill->forceFill(['status' => VendorBillStatus::Submitted])->save();

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->set('notes', 'Trying to edit a submitted bill')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertNotSame('Trying to edit a submitted bill', $bill->fresh()->notes);
    }

    public function test_vendor_bill_autosave_never_runs_for_a_read_only_role(): void
    {
        $this->actingAsRole('auditor');
        $bill = $this->draftVendorBill();

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->set('notes', 'An auditor should not be able to save this')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertSame(0, $bill->fresh()->draft_version);
    }

    // --- Vendor purchase order ----------------------------------------------

    private function draftVendorPurchaseOrder(): VendorPurchaseOrder
    {
        return VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'status' => VendorPurchaseOrderStatus::Draft,
            'number' => 'ACM-VPO-DRAFT-AUTOSAVE',
        ]);
    }

    public function test_vendor_purchase_order_autosave_persists_terms_and_bumps_version(): void
    {
        $this->actingAsRole('owner');
        $po = $this->draftVendorPurchaseOrder();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->set('terms', 'Net 30 from vendor')
            ->assertSet('autosaveStatus', 'saved');

        $fresh = $po->fresh();
        $this->assertSame('Net 30 from vendor', $fresh->terms);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_vendor_purchase_order_autosave_never_runs_once_approved(): void
    {
        $this->actingAsRole('owner');
        $po = $this->draftVendorPurchaseOrder();
        $po->forceFill(['status' => VendorPurchaseOrderStatus::Approved])->save();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->set('notes', 'Trying to edit an approved PO')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertNotSame('Trying to edit an approved PO', $po->fresh()->notes);
    }

    public function test_vendor_purchase_order_autosave_never_runs_for_a_read_only_role(): void
    {
        $this->actingAsRole('auditor');
        $po = $this->draftVendorPurchaseOrder();

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->set('terms', 'An auditor should not be able to save this')
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertSame(0, $po->fresh()->draft_version);
    }
}
