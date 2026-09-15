<?php

namespace Tests\Feature\Filament;

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Support\ActionQueue;
use App\Filament\Widgets\ActionQueueWidget;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The role-aware dashboard action queue — see docs/rebuild/DESIGN.md §3
 * and docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md
 * D10. Links go to plain resource index pages (no `?tableFilters=` deep
 * link this slice — see the class docblock).
 */
class ActionQueueTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);

        // Filament::setTenant() fires a TenantSet event carrying the
        // authenticated user — needs someone logged in first, even
        // though ActionQueue::for() takes its own $user param below and
        // doesn't otherwise depend on who's "currently" authenticated.
        $this->actingAs(User::factory()->create());
        Filament::setTenant($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_owner_sees_overdue_invoices_draft_jobs_and_quotations_awaiting_decision(): void
    {
        $owner = $this->makeUserWithRole('owner');

        $overdue = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001', 'due_date' => '2026-09-01',
        ]);
        $overdue->forceFill(['balance' => 100])->save();

        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'status' => QuotationStatus::Sent, 'number' => 'QUO-0001']);
        SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'status' => SalesOrderStatus::Draft]);

        $items = ActionQueue::for($owner, $this->company);

        $labels = array_column($items, 'label');
        $this->assertStringContainsString('1 customer invoices overdue', implode(' ', $labels));
        $this->assertStringContainsString('1 jobs awaiting approval', implode(' ', $labels));
        $this->assertStringContainsString('1 quotations awaiting a customer decision', implode(' ', $labels));

        foreach ($items as $item) {
            $this->assertNotNull($item['url']);
        }
    }

    public function test_auditor_sees_the_same_items_as_owner_but_with_no_links(): void
    {
        $owner = $this->makeUserWithRole('owner');
        $auditor = $this->makeUserWithRole('auditor');

        $overdue = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0002', 'due_date' => '2026-09-01',
        ]);
        $overdue->forceFill(['balance' => 100])->save();

        $ownerItems = ActionQueue::for($owner, $this->company);
        $auditorItems = ActionQueue::for($auditor, $this->company);

        $this->assertSame(array_column($ownerItems, 'label'), array_column($auditorItems, 'label'));

        foreach ($auditorItems as $item) {
            $this->assertNull($item['url']);
        }
    }

    public function test_a_company_scoped_role_only_sees_its_own_companys_records(): void
    {
        $owner = $this->makeUserWithRole('owner');

        $otherClient = Client::create(['company_id' => $this->otherCompany->id, 'name' => 'Other Client']);
        $otherOverdue = Invoice::create([
            'company_id' => $this->otherCompany->id, 'client_id' => $otherClient->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-OTHER', 'due_date' => '2026-09-01',
        ]);
        $otherOverdue->forceFill(['balance' => 999])->save();

        $items = ActionQueue::for($owner, $this->company);

        $this->assertSame([], $items);
    }

    public function test_the_widget_renders_for_an_owner_and_a_staff_user(): void
    {
        $owner = $this->makeUserWithRole('owner');
        $staff = $this->makeUserWithRole('staff');

        $this->actingAs($owner);
        Livewire::test(ActionQueueWidget::class)->assertOk();

        $this->actingAs($staff);
        Livewire::test(ActionQueueWidget::class)->assertOk();
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role, 'is_active' => true]);

        return $user;
    }
}
