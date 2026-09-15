<?php

namespace Tests\Feature\Documents;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Livewire\TallStackInvoiceForm;
use App\Livewire\TallStackQuotationForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 13 repair plan item 1 — the Invoice/Quotation Line Items tab's
 * add/edit form moved from an <x-modal wire="showItemModal"> round-trip to
 * an inline row in the table itself (App\Livewire\TallStackInvoiceForm/
 * TallStackQuotationForm). The underlying addItem()/editItem()/saveItem()/
 * deleteItem() validation and persistence shape is unchanged (still covered
 * by the pre-existing item CRUD paths exercised elsewhere) — this test
 * covers what's actually new: the `itemFormOpen`/`editingItemId` state
 * machine that now gates which row renders as an editable form, and the
 * new `cancelItemForm()` method that replaces the modal's own
 * `$set('showItemModal', false)` cancel.
 */
class InlineItemEditingTest extends TestCase
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

    // --- Invoice ------------------------------------------------------

    public function test_add_item_opens_the_inline_form_with_no_editing_id(): void
    {
        $invoice = $this->draftInvoice();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('addItem')
            ->assertSet('itemFormOpen', true)
            ->assertSet('editingItemId', null);
    }

    public function test_edit_item_opens_the_inline_form_prefilled_for_that_row(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('editItem', $item->id)
            ->assertSet('itemFormOpen', true)
            ->assertSet('editingItemId', $item->id)
            ->assertSet('item_title', 'Router')
            ->assertSet('item_quantity', 2.0)
            ->assertSet('item_unit_cost', 150.0);
    }

    public function test_cancel_item_form_closes_it_without_saving(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('editItem', $item->id)
            ->set('item_title', 'Renamed but not saved')
            ->call('cancelItemForm')
            ->assertSet('itemFormOpen', false)
            ->assertSet('editingItemId', null);

        $this->assertSame('Router', $item->fresh()->title);
    }

    public function test_saving_a_new_item_through_the_inline_add_row_closes_the_form_and_persists(): void
    {
        $invoice = $this->draftInvoice();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('addItem')
            ->set('item_title', 'Access point')
            ->set('item_quantity', 3)
            ->set('item_unit_cost', 500)
            ->call('saveItem')
            ->assertSet('itemFormOpen', false)
            ->assertSet('editingItemId', null);

        $this->assertSame(['Access point'], $invoice->fresh()->items->pluck('title')->all());
    }

    public function test_saving_an_edited_item_through_the_inline_row_closes_the_form_and_persists(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('editItem', $item->id)
            ->set('item_title', 'Router Pro')
            ->set('item_unit_cost', 250)
            ->call('saveItem')
            ->assertSet('itemFormOpen', false)
            ->assertSet('editingItemId', null);

        $item->refresh();
        $this->assertSame('Router Pro', $item->title);
        $this->assertSame(250.0, (float) $item->unit_cost);
    }

    // --- Quotation ------------------------------------------------------

    public function test_quotation_add_item_opens_the_inline_form_with_no_editing_id(): void
    {
        $quotation = $this->draftQuotation();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('addItem')
            ->assertSet('itemFormOpen', true)
            ->assertSet('editingItemId', null);
    }

    public function test_quotation_edit_item_opens_the_inline_form_prefilled_for_that_row(): void
    {
        $quotation = $this->draftQuotation();
        $item = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Cabling', 'quantity' => 10, 'unit_cost' => 20, 'sort_order' => 0]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('editItem', $item->id)
            ->assertSet('itemFormOpen', true)
            ->assertSet('editingItemId', $item->id)
            ->assertSet('item_title', 'Cabling');
    }

    public function test_quotation_cancel_item_form_closes_it_without_saving(): void
    {
        $quotation = $this->draftQuotation();
        $item = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Cabling', 'quantity' => 10, 'unit_cost' => 20, 'sort_order' => 0]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('addItem')
            ->set('item_title', 'Should not persist')
            ->call('cancelItemForm')
            ->assertSet('itemFormOpen', false);

        $this->assertSame(['Cabling'], $quotation->fresh()->items->pluck('title')->all());
    }

    public function test_quotation_saving_a_new_item_through_the_inline_add_row_closes_the_form_and_persists(): void
    {
        $quotation = $this->draftQuotation();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('addItem')
            ->set('item_title', 'Switch')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 800)
            ->call('saveItem')
            ->assertSet('itemFormOpen', false)
            ->assertSet('editingItemId', null);

        $this->assertSame(['Switch'], $quotation->fresh()->items->pluck('title')->all());
    }

    private function draftInvoice(): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-INLINE',
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
            'number' => 'ACM-QUO-INLINE',
            'pricing_mode' => 'exclusive',
            'job_type' => 'installation',
            'discount_is_percentage' => false,
        ]);
    }
}
