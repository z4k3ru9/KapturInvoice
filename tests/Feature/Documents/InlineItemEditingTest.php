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

    // --- Inline quick-edit (quantity/unit cost straight in the table cell,
    // no expand-to-a-form round trip) -------------------------------------

    public function test_inline_quantity_edit_updates_the_item_and_recalculates_totals(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('updateItemInline', $item->id, 'quantity', '5');

        $item->refresh();
        $this->assertSame(5.0, (float) $item->quantity);
        $this->assertSame(750.0, (float) $item->line_total);
        $this->assertSame(750.0, (float) $invoice->fresh()->subtotal);
        $this->assertSame(750.0, (float) $invoice->fresh()->total);
    }

    public function test_inline_unit_cost_edit_updates_the_item_and_recalculates_totals(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('updateItemInline', $item->id, 'unit_cost', '200');

        $item->refresh();
        $this->assertSame(200.0, (float) $item->unit_cost);
        $this->assertSame(400.0, (float) $item->line_total);
    }

    public function test_inline_edit_rejects_a_field_outside_the_quantity_unit_cost_whitelist(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('updateItemInline', $item->id, 'title', 'Hacked title');

        $this->assertSame('Router', $item->fresh()->title);
    }

    public function test_inline_edit_never_runs_once_the_invoice_leaves_draft_status(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);
        $invoice->update(['status' => InvoiceStatus::Issued]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('updateItemInline', $item->id, 'quantity', '99');

        $this->assertSame(2.0, (float) $item->fresh()->quantity);
    }

    public function test_inline_edit_rejects_an_invalid_value(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('updateItemInline', $item->id, 'quantity', '-5')
            ->assertHasErrors(['quantity']);

        $this->assertSame(2.0, (float) $item->fresh()->quantity);
    }

    public function test_quotation_inline_quantity_edit_updates_the_item_and_recalculates_totals(): void
    {
        $quotation = $this->draftQuotation();
        $item = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Cabling', 'quantity' => 10, 'unit_cost' => 20, 'sort_order' => 0]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('updateItemInline', $item->id, 'quantity', '15');

        $item->refresh();
        $this->assertSame(15.0, (float) $item->quantity);
        $this->assertSame(300.0, (float) $item->line_total);
        $this->assertSame(300.0, (float) $quotation->fresh()->subtotal);
    }

    // --- Draft-only guard on saveItem()/deleteItem() (previously missing
    // on Invoice/Quotation, unlike VendorBill/VendorPurchaseOrder) --------

    public function test_save_item_is_blocked_once_the_invoice_leaves_draft_status(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);
        $invoice->update(['status' => InvoiceStatus::Issued]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('editItem', $item->id)
            ->set('item_title', 'Should not persist')
            ->call('saveItem');

        $this->assertSame('Router', $item->fresh()->title);
    }

    public function test_delete_item_is_blocked_once_the_invoice_leaves_draft_status(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Router', 'quantity' => 2, 'unit_cost' => 150, 'sort_order' => 0]);
        $invoice->update(['status' => InvoiceStatus::Issued]);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $invoice])
            ->call('deleteItem', $item->id);

        $this->assertNotNull($item->fresh());
    }

    public function test_quotation_save_item_is_blocked_once_the_quotation_leaves_draft_status(): void
    {
        $quotation = $this->draftQuotation();
        $item = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Cabling', 'quantity' => 10, 'unit_cost' => 20, 'sort_order' => 0]);
        $quotation->update(['status' => QuotationStatus::Sent]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('editItem', $item->id)
            ->set('item_title', 'Should not persist')
            ->call('saveItem');

        $this->assertSame('Cabling', $item->fresh()->title);
    }

    public function test_quotation_delete_item_is_blocked_once_the_quotation_leaves_draft_status(): void
    {
        $quotation = $this->draftQuotation();
        $item = QuotationItem::create(['quotation_id' => $quotation->id, 'title' => 'Cabling', 'quantity' => 10, 'unit_cost' => 20, 'sort_order' => 0]);
        $quotation->update(['status' => QuotationStatus::Sent]);

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('deleteItem', $item->id);

        $this->assertNotNull($item->fresh());
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
