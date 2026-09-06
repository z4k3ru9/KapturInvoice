<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Filament\Resources\Vendors\RelationManagers\ContactsRelationManager as VendorContactsRelationManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for a real bug found via manual exploration (see
 * AdminPanelProvider): Filament defaults every relation manager shown on a
 * resource's View page to read-only
 * (Panel::hasReadOnlyRelationManagersOnResourceViewPagesByDefault()
 * defaults to true), which silently hides every Create/Edit/Delete action
 * — no error, the header-actions slot just renders empty. Since creating a
 * record redirects to its View page by default when one exists
 * (Filament\Resources\Pages\CreateRecord::getRedirectUrl() prefers `view`
 * over `edit`), and Clients/Vendors/Projects (modal-based resources — see
 * CLAUDE.md) have no Edit *page* to fall back to at all, this broke the
 * primary way to add contacts/items/tasks to a freshly created record.
 * Fixed by disabling the default in AdminPanelProvider; this test asserts
 * the Create action is actually visible on each affected relation manager
 * when mounted the way it really appears — on the View page, not just any
 * page class.
 */
class RelationManagerViewPageActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_client_contacts_create_action_is_visible_on_the_client_view_page(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertTableActionVisible('create');
    }

    public function test_vendor_contacts_create_action_is_visible_on_the_vendor_view_page(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);

        Livewire::test(VendorContactsRelationManager::class, ['ownerRecord' => $vendor, 'pageClass' => ViewVendor::class])
            ->assertTableActionVisible('create');
    }

    public function test_project_tasks_create_action_is_visible_on_the_project_view_page(): void
    {
        $project = Project::create(['company_id' => $this->company->id, 'name' => 'Test Project']);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->assertTableActionVisible('create');
    }

    public function test_invoice_items_create_action_is_visible_on_the_invoice_view_page(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'draft']);

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => ViewInvoice::class])
            ->assertTableActionVisible('create');
    }
}
