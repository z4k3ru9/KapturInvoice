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
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        foreach ([ClientResource::class, ProductResource::class, TaxRateResource::class, InvoiceResource::class] as $resource) {
            $this->get($resource::getUrl('create', tenant: $this->company))
                ->assertOk();
        }
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

        app(\App\Services\InvoiceTotalsCalculator::class)->syncItemTaxes($item, [$taxRate->id]);
        app(\App\Services\InvoiceTotalsCalculator::class)->recalculate($invoice->fresh());

        $invoice->refresh();

        $this->assertSame('200.00', (string) $invoice->subtotal);
        $this->assertSame('20.00', (string) $invoice->tax_total);
        $this->assertSame('220.00', (string) $invoice->total);
        $this->assertSame('220.00', (string) $invoice->balance);
    }

    public function test_contact_relation_manager_lists_the_clients_contacts(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'is_primary' => true]);

        \Livewire\Livewire::test(ContactsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])->assertCanSeeTableRecords([$contact]);
    }
}
