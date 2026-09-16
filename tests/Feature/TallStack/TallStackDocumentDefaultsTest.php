<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Livewire\TallStackInvoiceForm;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackRecurringInvoiceForm;
use App\Livewire\TallStackSettingsNumbering;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Repair plan Phase 11 (decision gate G5):
 *
 * 1. The `number` field is hidden entirely on every create form — it was a
 *    visible `<x-input>` even though App\Services\DocumentNumberGenerator
 *    already silently auto-assigns it whenever blank at save time. Kept
 *    visible and editable on edit, since a manually typed number on an
 *    already-saved document must stay correctable ("a manually typed
 *    number is respected and doesn't consume the sequence" — CLAUDE.md).
 * 2. `Company::default_expire_after_days` is new (the only genuinely
 *    missing settings field — `default_payment_terms` and
 *    `payment_instructions` already existed, just unwired; see
 *    App\Livewire\TallStackSettingsNumbering's docblock). It prefills a
 *    new Quotation's `valid_until`. `default_payment_terms` prefills the
 *    `terms` field on every create form that has one (Invoice, Quotation,
 *    Recurring Invoice template, Vendor Purchase Order) — never on edit,
 *    so a real saved value is never overwritten.
 */
class TallStackDocumentDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private User $user;

    private const HIDDEN_NUMBER_HINT = 'Leave blank to auto-assign from the company numbering sequence.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create([
            'name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD',
            'default_payment_terms' => 'Payment due within 14 days of invoice date.',
            'default_expire_after_days' => 30,
        ])->fresh();
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($this->user);
    }

    // --- Number field hidden on create, visible on edit -----------------

    public function test_invoice_create_form_hides_the_number_field(): void
    {
        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company])
            ->assertDontSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_invoice_edit_form_still_shows_the_number_field(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice, 'status' => InvoiceStatus::Draft, 'number' => 'ACM-INV-0001',
        ])->fresh();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->assertSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_quotation_create_form_hides_the_number_field(): void
    {
        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company])
            ->assertDontSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_quotation_edit_form_still_shows_the_number_field(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'number' => 'ACM-QUO-0001', 'status' => 'draft',
        ])->fresh();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->assertSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_recurring_invoice_create_form_hides_the_number_field(): void
    {
        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company])
            ->assertDontSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_recurring_invoice_edit_form_still_shows_the_number_field(): void
    {
        $template = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice, 'status' => InvoiceStatus::Draft, 'number' => 'ACM-INV-0002',
            'is_recurring' => true, 'recurring_frequency' => 'monthly',
        ])->fresh();

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $template])
            ->assertSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_vendor_bill_create_form_hides_the_number_field(): void
    {
        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company])
            ->assertDontSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_vendor_bill_edit_form_still_shows_the_number_field(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor Co']);
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-PO-0001', 'status' => 'draft',
        ]);
        $bill = VendorBill::create([
            'company_id' => $this->company->id, 'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VB-0001', 'status' => 'draft',
        ]);

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->assertSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_vendor_purchase_order_create_form_hides_the_number_field(): void
    {
        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company])
            ->assertDontSee(self::HIDDEN_NUMBER_HINT);
    }

    public function test_vendor_purchase_order_edit_form_still_shows_the_number_field(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor Co']);
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-PO-0002', 'status' => 'draft',
        ]);

        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company, 'vendorPurchaseOrder' => $po])
            ->assertSee(self::HIDDEN_NUMBER_HINT);
    }

    // --- Default Terms prefill on create, never on edit ------------------

    public function test_invoice_create_prefills_terms_from_the_company_default(): void
    {
        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company])
            ->assertSet('terms', 'Payment due within 14 days of invoice date.');
    }

    public function test_invoice_edit_does_not_overwrite_a_real_saved_terms_value(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice, 'status' => InvoiceStatus::Draft, 'number' => 'ACM-INV-0003',
            'terms' => 'Custom negotiated terms for this client.',
        ])->fresh();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->assertSet('terms', 'Custom negotiated terms for this client.');
    }

    public function test_quotation_create_prefills_terms_and_valid_until_from_company_defaults(): void
    {
        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company])
            ->assertSet('terms', 'Payment due within 14 days of invoice date.')
            ->assertSet('valid_until', now()->addDays(30)->toDateString());
    }

    public function test_quotation_create_falls_back_to_a_14_day_default_when_no_company_default_is_set(): void
    {
        $company = Company::create(['name' => 'No Default Co', 'slug' => 'no-default-co', 'currency_code' => 'USD']);
        $company->users()->attach($this->user, ['role' => 'owner']);

        Livewire::test(TallStackQuotationForm::class, ['company' => $company])
            ->assertSet('valid_until', now()->addDays(14)->toDateString());
    }

    public function test_quotation_edit_does_not_overwrite_a_real_saved_valid_until(): void
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'number' => 'ACM-QUO-0002', 'status' => 'draft', 'valid_until' => '2026-01-01',
        ])->fresh();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->assertSet('valid_until', '2026-01-01');
    }

    public function test_recurring_invoice_create_prefills_terms_from_the_company_default(): void
    {
        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company])
            ->assertSet('terms', 'Payment due within 14 days of invoice date.');
    }

    public function test_vendor_purchase_order_create_prefills_terms_from_the_company_default(): void
    {
        Livewire::test(TallStackVendorPurchaseOrderForm::class, ['company' => $this->company])
            ->assertSet('terms', 'Payment due within 14 days of invoice date.');
    }

    // --- Settings ---------------------------------------------------------

    public function test_numbering_settings_saves_default_expire_after_days(): void
    {
        Livewire::test(TallStackSettingsNumbering::class, ['company' => $this->company])
            ->assertSet('default_expire_after_days', 30)
            ->set('default_expire_after_days', 45)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(45, $this->company->fresh()->default_expire_after_days);
    }

    public function test_numbering_settings_rejects_a_non_positive_expire_after_days(): void
    {
        Livewire::test(TallStackSettingsNumbering::class, ['company' => $this->company])
            ->set('default_expire_after_days', 0)
            ->call('save')
            ->assertHasErrors(['default_expire_after_days']);
    }
}
