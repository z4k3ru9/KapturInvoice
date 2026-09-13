<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Credits\CreditResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceTotalsCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke-tests the scaffolded admin panel: a user can log in, land inside a
 * tenant (Company), and every core resource's list/create/edit pages render
 * without a server error — including the tenant-scoping added by
 * App\Models\Concerns\BelongsToCompany and the Invoice items relation
 * manager's tax handling.
 */
class AdminPanelResourcesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->otherCompany->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_resource_index_pages_render(): void
    {
        foreach ([ClientResource::class, ProductResource::class, TaxRateResource::class, InvoiceResource::class, CreditResource::class, PaymentResource::class] as $resource) {
            $this->get($resource::getUrl('index', tenant: $this->company))
                ->assertOk();
        }
    }

    public function test_resource_create_pages_render(): void
    {
        // Only Invoice keeps a dedicated Create page (it needs one to host
        // the Items relation manager after creating) — Client/Product/
        // TaxRate dropped theirs in favor of a modal, covered by
        // ModalCreateEditTest instead. See docs/filament-admin-layout-design.md §6.
        $this->get(InvoiceResource::getUrl('create', tenant: $this->company))
            ->assertOk();
    }

    public function test_client_belongs_to_company_scope_hides_other_tenants_records(): void
    {
        Client::create(['company_id' => $this->company->id, 'name' => 'Own Client']);

        Filament::setTenant($this->otherCompany);
        Client::create(['company_id' => $this->otherCompany->id, 'name' => 'Other Client']);
        Filament::setTenant($this->company);

        $this->get(ClientResource::getUrl('index', tenant: $this->company))
            ->assertOk()
            ->assertSee('Own Client')
            ->assertDontSee('Other Client');
    }

    public function test_invoice_edit_page_renders_with_items_relation_manager(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $this->get(InvoiceResource::getUrl('edit', ['record' => $invoice], tenant: $this->company))
            ->assertOk();
    }

    public function test_invoice_totals_recalculate_when_items_and_taxes_change(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Widget', 'unit_cost' => 100]);
        $taxRate = TaxRate::create(['company_id' => $this->company->id, 'name' => 'VAT', 'rate' => 10]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'title' => $product->name,
            'quantity' => 2,
            'unit_cost' => 100,
        ]);

        app(InvoiceTotalsCalculator::class)->syncItemTaxes($item, [$taxRate->id]);
        app(InvoiceTotalsCalculator::class)->recalculate($invoice->fresh());

        $invoice->refresh();

        $this->assertSame('200.00', (string) $invoice->subtotal);
        $this->assertSame('20.00', (string) $invoice->tax_total);
        $this->assertSame('220.00', (string) $invoice->total);
        $this->assertSame('220.00', (string) $invoice->balance);
    }

    /**
     * Regression test for the discount-order bug flagged in
     * docs/REFACTOR_PLAN.md §1.3: a document/invoice-level discount must
     * reduce the taxable base before tax is applied, not just the post-tax
     * total. 100 units @ 100 = 10,000 subtotal, 10% document discount =
     * 9,000 taxable, 10% VAT on 9,000 = 900 tax, total 9,900 — not
     * 10,000 - 1,000 + 1,000 (tax on the undiscounted subtotal) = 10,000.
     */
    public function test_document_level_discount_reduces_the_taxable_base_before_tax(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Widget', 'unit_cost' => 100]);
        $taxRate = TaxRate::create(['company_id' => $this->company->id, 'name' => 'VAT', 'rate' => 10]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'discount' => 10,
            'discount_is_percentage' => true,
        ]);

        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'title' => $product->name,
            'quantity' => 100,
            'unit_cost' => 100,
        ]);

        $calculator = app(InvoiceTotalsCalculator::class);
        $calculator->syncItemTaxes($item, [$taxRate->id]);
        $calculator->recalculate($invoice->fresh());

        $invoice->refresh();

        $this->assertSame('10000.00', (string) $invoice->subtotal);
        $this->assertSame('900.00', (string) $invoice->tax_total);
        $this->assertSame('9900.00', (string) $invoice->total);

        // Recalculating again must be idempotent — it must not re-apply the
        // document discount on top of the already-adjusted tax amount.
        $calculator->recalculate($invoice->fresh());
        $invoice->refresh();

        $this->assertSame('900.00', (string) $invoice->tax_total);
        $this->assertSame('9900.00', (string) $invoice->total);
    }

    public function test_contact_relation_manager_lists_the_clients_contacts(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'is_primary' => true]);

        Livewire::test(ContactsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])->assertCanSeeTableRecords([$contact]);
    }
}
