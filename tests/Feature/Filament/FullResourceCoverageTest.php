<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Credits\CreditResource;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PriceListItems\PriceListItemResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\ProposalSnippets\ProposalSnippetResource;
use App\Filament\Resources\ProposalTemplates\ProposalTemplateResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\RecurringInvoices\RecurringInvoiceResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\TaskStatuses\TaskStatusResource;
use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\CompaniesRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Filament\Resources\Vendors\RelationManagers\ContactsRelationManager as VendorContactsRelationManager;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalSnippet;
use App\Models\ProposalTemplate;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The one designed-for-completeness sweep: every Filament resource's every
 * declared page (index/create/view/edit, whichever it actually registers —
 * discovered from Resource::getPages() rather than hardcoded, so a page
 * added later is automatically covered) renders for a real record, plus
 * the two tenancy pages and the relation managers not already exercised by
 * a more specific test elsewhere (Invoice Items, Project Tasks, Client/
 * Documents already covered in their own domain test files).
 *
 * This does not replace the behavior-specific tests (numbering, mailer,
 * gateway driver, converters, …) elsewhere — it's the net that catches "a
 * page 500s because of a missing relation/field" across the whole panel,
 * the kind of bug a narrower test wouldn't stumble into.
 */
class FullResourceCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'currency_code' => 'USD',
            'invoice_prefix' => 'INV-',
            'invoice_next_number' => 1,
            'quote_prefix' => 'QUO-',
            'quote_next_number' => 1,
            'credit_prefix' => 'CRE-',
            'credit_next_number' => 1,
        ]);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_every_resource_page_renders_for_a_real_record(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Widget', 'unit_cost' => 100]);
        $taxRate = TaxRate::create(['company_id' => $this->company->id, 'name' => 'VAT', 'rate' => 10]);
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'draft', 'number' => 'INV-0001']);
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'type' => 'quote', 'status' => 'draft', 'number' => 'QUO-0001']);
        $recurring = Invoice::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'draft', 'is_recurring' => true]);
        $credit = Credit::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'amount' => 50]);
        $payment = Payment::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'invoice_id' => $invoice->id, 'amount' => 50]);
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
        $expenseCategory = ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Office']);
        $expense = Expense::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'expense_category_id' => $expenseCategory->id, 'subtotal' => 20]);
        $project = Project::create(['company_id' => $this->company->id, 'name' => 'Website revamp']);
        $taskStatus = TaskStatus::create(['company_id' => $this->company->id, 'name' => 'To do']);
        $gateway = PaymentGateway::create(['company_id' => $this->company->id, 'name' => 'Local API', 'driver' => 'local_api']);
        $proposalTemplate = ProposalTemplate::create(['company_id' => $this->company->id, 'name' => 'Standard SOW']);
        $proposalSnippet = ProposalSnippet::create(['company_id' => $this->company->id, 'name' => 'Terms block']);
        $proposal = Proposal::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'title' => 'Website redesign', 'amount' => 500]);
        $priceListItem = PriceListItem::create(['company_id' => $this->company->id, 'brand' => 'Hikvision', 'sku' => 'DS-TEST-1']);
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'number' => 'KA-QUO-P3-0001', 'status' => 'draft']);
        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'KA-SO-P3-0001',
            'status' => 'draft',
            'approved_value' => 0,
        ]);
        $otherUser = User::factory()->create();
        $this->company->users()->attach($otherUser, ['role' => 'member']);

        $cases = [
            [ClientResource::class, $client],
            [ProductResource::class, $product],
            [TaxRateResource::class, $taxRate],
            [InvoiceResource::class, $invoice],
            [QuoteResource::class, $quote],
            [RecurringInvoiceResource::class, $recurring],
            [CreditResource::class, $credit],
            [PaymentResource::class, $payment],
            [VendorResource::class, $vendor],
            [ExpenseCategoryResource::class, $expenseCategory],
            [ExpenseResource::class, $expense],
            [ProjectResource::class, $project],
            [TaskStatusResource::class, $taskStatus],
            [PaymentGatewayResource::class, $gateway],
            [ProposalTemplateResource::class, $proposalTemplate],
            [ProposalSnippetResource::class, $proposalSnippet],
            [ProposalResource::class, $proposal],
            [PriceListItemResource::class, $priceListItem],
            [QuotationResource::class, $quotation],
            [SalesOrderResource::class, $salesOrder],
            [UserResource::class, $otherUser],
        ];

        foreach ($cases as [$resourceClass, $record]) {
            $this->assertResourcePagesRender($resourceClass, $record);
        }
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function assertResourcePagesRender(string $resourceClass, Model $record): void
    {
        $pages = $resourceClass::getPages();

        foreach (['index', 'create', 'view', 'edit'] as $pageKey) {
            if (! array_key_exists($pageKey, $pages)) {
                continue;
            }

            $parameters = in_array($pageKey, ['view', 'edit'], true) ? ['record' => $record] : [];

            $this->get($resourceClass::getUrl($pageKey, $parameters, tenant: $this->company))
                ->assertOk("{$resourceClass}::{$pageKey} did not render");
        }
    }

    public function test_tenancy_pages_render(): void
    {
        $this->get(Filament::getTenantProfileUrl())->assertOk();
        $this->get(Filament::getTenantRegistrationUrl())->assertOk();
    }

    public function test_vendor_contacts_relation_manager_renders(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
        $contact = VendorContact::create(['vendor_id' => $vendor->id, 'first_name' => 'Jane']);

        Livewire::test(VendorContactsRelationManager::class, [
            'ownerRecord' => $vendor,
            'pageClass' => ViewVendor::class,
        ])->assertCanSeeTableRecords([$contact]);
    }

    public function test_companies_relation_manager_renders_for_a_user(): void
    {
        $member = User::factory()->create();
        $this->company->users()->attach($member, ['role' => 'member']);

        Livewire::test(CompaniesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditUser::class,
        ])->assertCanSeeTableRecords([$this->company]);
    }
}
