<?php

namespace Tests\Feature\Documents;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\UnitOfMeasure;
use App\Livewire\TallStackInvoiceForm;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackRecurringInvoiceForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two new forward-looking business rules wired into Invoice/Quotation/
 * Recurring Invoice line items (Proposals are out of scope — free-form
 * HTML/CSS documents with no structured line items):
 *
 * 1. Quantity must be a whole number going forward — an explicit decision
 *    to NOT change the `quantity` column type (still decimal(15,4), since
 *    real imported legacy data may carry genuinely fractional quantities
 *    like "2.5 hours" and issued/historical documents are never altered).
 *    This only constrains new/edited line items via the Livewire forms.
 * 2. A defined `unit` field (App\Enums\UnitOfMeasure) per line item,
 *    autofilled from the selected Product and freely editable afterward,
 *    same pattern as TallStackProducts's own unit dropdown.
 */
class LineItemQuantityAndUnitTest extends TestCase
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

    // --- Invoice: quantity must be a whole number -------------------------

    public function test_invoice_save_item_rejects_a_fractional_quantity(): void
    {
        $invoice = $this->draftInvoice();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('addItem')
            ->set('item_title', 'Cabling')
            ->set('item_quantity', 2.5)
            ->set('item_unit_cost', 100)
            ->call('saveItem')
            ->assertHasErrors(['item_quantity']);

        $this->assertSame(0, $invoice->items()->count());
    }

    public function test_invoice_save_item_accepts_a_whole_number_quantity(): void
    {
        $invoice = $this->draftInvoice();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('addItem')
            ->set('item_title', 'Router')
            ->set('item_quantity', 3)
            ->set('item_unit_cost', 100)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(3, (int) $invoice->items()->first()->quantity);
    }

    public function test_invoice_inline_quick_edit_rejects_a_fractional_quantity(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('updateItemInline', $item->id, 'quantity', '2.5')
            ->assertHasErrors(['quantity']);

        $this->assertSame(2, (int) $item->fresh()->quantity);
    }

    public function test_invoice_correction_item_quantity_rejects_a_fractional_value(): void
    {
        $invoice = $this->draftInvoice();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $invoice = $invoice->fresh(['items']);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('openCorrectionModal', 'amend')
            ->set('correctionReason', 'Fixing a typo')
            ->set('correctionItems.0.quantity', 1.5)
            ->call('submitCorrection')
            ->assertHasErrors(['correctionItems.0.quantity']);
    }

    // --- Quotation: quantity must be a whole number ------------------------

    public function test_quotation_save_item_rejects_a_fractional_quantity(): void
    {
        $quotation = $this->draftQuotation();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('addItem')
            ->set('item_title', 'Cabling')
            ->set('item_quantity', 10.25)
            ->set('item_unit_cost', 20)
            ->call('saveItem')
            ->assertHasErrors(['item_quantity']);

        $this->assertSame(0, $quotation->items()->count());
    }

    public function test_quotation_inline_quick_edit_rejects_a_fractional_quantity(): void
    {
        $quotation = $this->draftQuotation();
        $item = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Cabling', 'quantity' => 10, 'unit_cost' => 20, 'sort_order' => 0]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('updateItemInline', $item->id, 'quantity', '10.5')
            ->assertHasErrors(['quantity']);
    }

    // --- Recurring invoice template: quantity must be a whole number -------

    public function test_recurring_invoice_save_item_rejects_a_fractional_quantity(): void
    {
        $template = $this->draftRecurringTemplate();

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $template])
            ->call('addItem')
            ->set('item_title', 'Monthly hosting')
            ->set('item_quantity', 1.5)
            ->set('item_unit_cost', 500)
            ->call('saveItem')
            ->assertHasErrors(['item_quantity']);

        $this->assertSame(0, $template->items()->count());
    }

    // --- Unit field: autofill from product, persisted, editable ------------

    public function test_invoice_item_unit_autofills_from_the_selected_product_and_is_saved(): void
    {
        $invoice = $this->draftInvoice();
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Installation service', 'unit' => 'hour', 'unit_cost' => 150]);

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('addItem')
            ->set('item_product_id', (string) $product->id)
            ->assertSet('item_unit', UnitOfMeasure::Hour->value)
            ->set('item_quantity', 4)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(UnitOfMeasure::Hour, $invoice->items()->first()->unit);

        // Still freely editable afterward, same as the other autofilled fields.
        $item = $invoice->items()->first();
        $component->call('editItem', $item->id)
            ->set('item_unit', UnitOfMeasure::Day->value)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(UnitOfMeasure::Day, $item->fresh()->unit);
    }

    public function test_quotation_item_unit_autofills_from_the_selected_product_and_is_saved(): void
    {
        $quotation = $this->draftQuotation();
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Cable roll', 'unit' => 'roll', 'unit_cost' => 45]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('addItem')
            ->set('item_product_id', (string) $product->id)
            ->assertSet('item_unit', UnitOfMeasure::Roll->value)
            ->set('item_quantity', 2)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(UnitOfMeasure::Roll, $quotation->items()->first()->unit);
    }

    public function test_recurring_invoice_item_unit_autofills_from_the_selected_product_and_is_saved(): void
    {
        $template = $this->draftRecurringTemplate();
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Support license', 'unit' => 'license', 'unit_cost' => 200]);

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $template])
            ->call('addItem')
            ->set('item_product_id', (string) $product->id)
            ->assertSet('item_unit', UnitOfMeasure::License->value)
            ->set('item_quantity', 1)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame(UnitOfMeasure::License, $template->items()->first()->unit);
    }

    public function test_invoice_item_unit_is_rejected_when_not_a_recognized_unit_of_measure(): void
    {
        $invoice = $this->draftInvoice();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('addItem')
            ->set('item_title', 'Router')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 100)
            ->set('item_unit', 'not-a-real-unit')
            ->call('saveItem')
            ->assertHasErrors(['item_unit']);
    }

    // --- PDF: unit shown next to quantity -----------------------------

    public function test_invoice_pdf_shows_the_unit_abbreviation_next_to_quantity(): void
    {
        $invoice = $this->draftInvoice();
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Installation labor',
            'quantity' => 8,
            'unit' => UnitOfMeasure::Hour,
            'unit_cost' => 150,
            'line_total' => 1200,
        ]);
        $invoice = $invoice->fresh();
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('hour', $html);
    }

    /** Unit is rendered inline after the quantity (matching vendor-bill/vendor-purchase-order and the admin item tables' own "20 roll" convention — see docs/out-of-scope-findings.md's PDF-unit-column-style entry), so a legacy item with no unit shows a bare quantity and no dangling suffix at all. */
    public function test_invoice_pdf_shows_a_bare_quantity_when_a_legacy_item_has_no_unit(): void
    {
        $invoice = $this->draftInvoice();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Legacy item', 'quantity' => 1, 'unit_cost' => 100, 'line_total' => 100]);
        $invoice = $invoice->fresh();
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('>1</td>', $html);
    }

    public function test_quotation_pdf_shows_the_unit_abbreviation_next_to_quantity(): void
    {
        $quotation = $this->draftQuotation();
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Cable roll',
            'quantity' => 3,
            'unit' => UnitOfMeasure::Roll,
            'unit_cost' => 45,
            'line_total' => 135,
        ]);
        $quotation = $quotation->fresh();
        $quotation->loadMissing('client', 'company', 'items.product');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString('roll', $html);
    }

    // --- Helpers ------------------------------------------------------

    private function draftInvoice(): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-QTYUNIT',
            'currency_code' => 'USD',
            'discount_is_percentage' => false,
        ]);
    }

    private function draftQuotation(): Quotation
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        return Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'status' => QuotationStatus::Draft,
            'number' => 'ACM-QUO-QTYUNIT',
            'pricing_mode' => 'exclusive',
            'job_type' => 'installation',
            'discount_is_percentage' => false,
        ]);
    }

    private function draftRecurringTemplate(): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-RECURQTYUNIT',
            'currency_code' => 'USD',
            'discount_is_percentage' => false,
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
        ]);
    }
}
