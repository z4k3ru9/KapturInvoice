<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Credits\CreditResource;
use App\Filament\Resources\Credits\Pages\ListCredits;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Resources\PaymentGateways\Pages\ListPaymentGateways;
use App\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PriceListItems\Pages\ListPriceListItems;
use App\Filament\Resources\PriceListItems\PriceListItemResource;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\Pages\ListProposals;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\ProposalSnippets\Pages\ListProposalSnippets;
use App\Filament\Resources\ProposalSnippets\ProposalSnippetResource;
use App\Filament\Resources\ProposalTemplates\Pages\ListProposalTemplates;
use App\Filament\Resources\ProposalTemplates\ProposalTemplateResource;
use App\Filament\Resources\TaskStatuses\Pages\ListTaskStatuses;
use App\Filament\Resources\TaskStatuses\TaskStatusResource;
use App\Filament\Resources\TaxRates\Pages\ListTaxRates;
use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\ExpenseCategory;
use App\Models\PaymentGateway;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalSnippet;
use App\Models\ProposalTemplate;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The 14 resources listed in docs/filament-admin-layout-design.md §6
 * dropped their dedicated Create/Edit pages in favor of Filament's
 * built-in modal fallback (no page registered for that action name —
 * see the resources' own getPages() comments) — better for focused, one-
 * screen data entry, especially on mobile. This asserts, for each one,
 * that the page really is gone AND that the modal create+edit round-trip
 * still actually works (including the two with special create-time logic:
 * Credit's numbering, replicated via ListCredits' CreateAction::mutateDataUsing()).
 *
 * Resources with a relation manager that needs a real page to live on
 * (Invoices/Quotes/RecurringInvoices/Expenses' Items/Documents, Users'
 * Companies) deliberately keep their pages — not covered here.
 */
class ModalCreateEditTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create([
            'name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD',
            'credit_prefix' => 'CRE-', 'credit_next_number' => 1,
        ]);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    private function assertNoCreateOrEditPage(string $resourceClass): void
    {
        $pages = $resourceClass::getPages();

        $this->assertArrayNotHasKey('create', $pages, "{$resourceClass} still has a Create page");
        $this->assertArrayNotHasKey('edit', $pages, "{$resourceClass} still has an Edit page");
    }

    public function test_client_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ClientResource::class);

        Livewire::test(ListClients::class)
            ->callAction('create', data: ['name' => 'Modal Co'])
            ->assertHasNoActionErrors();

        $client = Client::where('name', 'Modal Co')->firstOrFail();

        Livewire::test(ListClients::class)
            ->callTableAction('edit', $client, data: ['name' => 'Modal Co Renamed'])
            ->assertHasNoActionErrors();

        $this->assertSame('Modal Co Renamed', $client->fresh()->name);
    }

    public function test_vendor_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(VendorResource::class);

        Livewire::test(ListVendors::class)
            ->callAction('create', data: ['name' => 'Modal Vendor'])
            ->assertHasNoActionErrors();

        $vendor = Vendor::where('name', 'Modal Vendor')->firstOrFail();

        Livewire::test(ListVendors::class)
            ->callTableAction('edit', $vendor, data: ['name' => 'Modal Vendor Renamed'])
            ->assertHasNoActionErrors();

        $this->assertSame('Modal Vendor Renamed', $vendor->fresh()->name);
    }

    public function test_project_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ProjectResource::class);

        Livewire::test(ListProjects::class)
            ->callAction('create', data: ['name' => 'Modal Project'])
            ->assertHasNoActionErrors();

        $project = Project::where('name', 'Modal Project')->firstOrFail();

        Livewire::test(ListProjects::class)
            ->callTableAction('edit', $project, data: ['name' => 'Modal Project Renamed'])
            ->assertHasNoActionErrors();

        $this->assertSame('Modal Project Renamed', $project->fresh()->name);
    }

    public function test_product_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ProductResource::class);

        Livewire::test(ListProducts::class)
            ->callAction('create', data: ['name' => 'Widget', 'unit_cost' => 10])
            ->assertHasNoActionErrors();

        $product = Product::where('name', 'Widget')->firstOrFail();

        Livewire::test(ListProducts::class)
            ->callTableAction('edit', $product, data: ['unit_cost' => 15])
            ->assertHasNoActionErrors();

        $this->assertSame('15.0000', (string) $product->fresh()->unit_cost);
    }

    public function test_tax_rate_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(TaxRateResource::class);

        Livewire::test(ListTaxRates::class)
            ->callAction('create', data: ['name' => 'VAT', 'rate' => 10])
            ->assertHasNoActionErrors();

        $taxRate = TaxRate::where('name', 'VAT')->firstOrFail();

        Livewire::test(ListTaxRates::class)
            ->callTableAction('edit', $taxRate, data: ['rate' => 12])
            ->assertHasNoActionErrors();

        $this->assertSame('12.000', (string) $taxRate->fresh()->rate);
    }

    /**
     * "New credit-note creation ... remain deferred" —
     * FINALIZED-DECISIONS.md §7. ListCredits no longer exposes a Create
     * action at all (docs/REFACTOR_PLAN.md drift audit) — this now only
     * proves the edit modal still works for an existing/legacy-imported
     * credit, seeded directly rather than through the (removed) UI flow.
     */
    public function test_credit_has_no_create_action_but_its_edit_modal_still_works(): void
    {
        $this->assertNoCreateOrEditPage(CreditResource::class);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $credit = Credit::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'amount' => 50]);

        Livewire::test(ListCredits::class)
            ->assertActionDoesNotExist('create');

        Livewire::test(ListCredits::class)
            ->callTableAction('edit', $credit, data: ['amount' => 75])
            ->assertHasNoActionErrors();

        $this->assertSame('75.00', (string) $credit->fresh()->amount);
    }

    public function test_payment_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(PaymentResource::class);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);

        Livewire::test(ListPayments::class)
            ->callAction('create', data: ['client_id' => $client->id, 'amount' => 100])
            ->assertHasNoActionErrors();

        $payment = $client->payments()->firstOrFail();

        Livewire::test(ListPayments::class)
            ->callTableAction('edit', $payment, data: ['amount' => 120])
            ->assertHasNoActionErrors();

        $this->assertSame('120.00', (string) $payment->fresh()->amount);
    }

    public function test_payment_gateway_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(PaymentGatewayResource::class);

        Livewire::test(ListPaymentGateways::class)
            ->callAction('create', data: [
                'name' => 'Local API',
                'driver' => 'local_api',
                'config.base_url' => 'https://api.example-id-gateway.test',
            ])
            ->assertHasNoActionErrors();

        $gateway = PaymentGateway::where('name', 'Local API')->firstOrFail();

        Livewire::test(ListPaymentGateways::class)
            ->callTableAction('edit', $gateway, data: ['is_enabled' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue($gateway->fresh()->is_enabled);
    }

    public function test_proposal_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ProposalResource::class);

        Livewire::test(ListProposals::class)
            ->callAction('create', data: ['title' => 'Website redesign', 'amount' => 500])
            ->assertHasNoActionErrors();

        $proposal = Proposal::where('title', 'Website redesign')->firstOrFail();

        Livewire::test(ListProposals::class)
            ->callTableAction('edit', $proposal, data: ['amount' => 600])
            ->assertHasNoActionErrors();

        $this->assertSame('600.00', (string) $proposal->fresh()->amount);
    }

    public function test_expense_category_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ExpenseCategoryResource::class);

        Livewire::test(ListExpenseCategories::class)
            ->callAction('create', data: ['name' => 'Office'])
            ->assertHasNoActionErrors();

        $category = ExpenseCategory::where('name', 'Office')->firstOrFail();

        Livewire::test(ListExpenseCategories::class)
            ->callTableAction('edit', $category, data: ['name' => 'Office Supplies'])
            ->assertHasNoActionErrors();

        $this->assertSame('Office Supplies', $category->fresh()->name);
    }

    public function test_task_status_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(TaskStatusResource::class);

        Livewire::test(ListTaskStatuses::class)
            ->callAction('create', data: ['name' => 'To do'])
            ->assertHasNoActionErrors();

        $status = TaskStatus::where('name', 'To do')->firstOrFail();

        Livewire::test(ListTaskStatuses::class)
            ->callTableAction('edit', $status, data: ['name' => 'Backlog'])
            ->assertHasNoActionErrors();

        $this->assertSame('Backlog', $status->fresh()->name);
    }

    public function test_proposal_template_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ProposalTemplateResource::class);

        Livewire::test(ListProposalTemplates::class)
            ->callAction('create', data: ['name' => 'Standard SOW'])
            ->assertHasNoActionErrors();

        $template = ProposalTemplate::where('name', 'Standard SOW')->firstOrFail();

        Livewire::test(ListProposalTemplates::class)
            ->callTableAction('edit', $template, data: ['name' => 'Standard SOW v2'])
            ->assertHasNoActionErrors();

        $this->assertSame('Standard SOW v2', $template->fresh()->name);
    }

    public function test_proposal_snippet_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(ProposalSnippetResource::class);

        Livewire::test(ListProposalSnippets::class)
            ->callAction('create', data: ['name' => 'Terms block'])
            ->assertHasNoActionErrors();

        $snippet = ProposalSnippet::where('name', 'Terms block')->firstOrFail();

        Livewire::test(ListProposalSnippets::class)
            ->callTableAction('edit', $snippet, data: ['name' => 'Terms block v2'])
            ->assertHasNoActionErrors();

        $this->assertSame('Terms block v2', $snippet->fresh()->name);
    }

    public function test_price_list_item_create_and_edit_modals_work(): void
    {
        $this->assertNoCreateOrEditPage(PriceListItemResource::class);

        Livewire::test(ListPriceListItems::class)
            ->callAction('create', data: ['brand' => 'Hikvision', 'sku' => 'DS-TEST-1'])
            ->assertHasNoActionErrors();

        $item = PriceListItem::where('sku', 'DS-TEST-1')->firstOrFail();

        Livewire::test(ListPriceListItems::class)
            ->callTableAction('edit', $item, data: ['sku' => 'DS-TEST-1-REV2'])
            ->assertHasNoActionErrors();

        $this->assertSame('DS-TEST-1-REV2', $item->fresh()->sku);
    }
}
