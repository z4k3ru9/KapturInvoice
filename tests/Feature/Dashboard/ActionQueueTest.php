<?php

namespace Tests\Feature\Dashboard;

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Dashboard\ActionQueue;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The role-aware dashboard action queue — see docs/rebuild/DESIGN.md §3
 * and docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md
 * D10. Links go to plain TALL-stack register/index pages (no
 * `?tableFilters=` deep link this slice — see the class docblock).
 *
 * Ported from tests/Feature/Filament/ActionQueueTest.php during the
 * Filament-removal Phase B — the pure ActionQueue::for() assertions only;
 * that file's own Filament-widget render test is dropped, since
 * App\Filament\Widgets\ActionQueueWidget no longer exists — see the
 * Phase B report's runtime verification of App\Livewire\TallStackDashboard
 * (the /tall/{company:slug}/dashboard route) for the equivalent manual
 * check; no dedicated automated test of that page existed before this
 * removal either.
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

        $this->actingAs(User::factory()->create());
        app(Tenancy::class)->set($this->company);
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
        $counts = array_column($items, 'count');
        // The count is shown once, as the item's own badge - the label
        // itself no longer repeats it (a duplicate "1 quotations awaiting..."
        // caught via UI screenshot review, also grammatically wrong for n=1).
        $this->assertStringContainsString('Customer invoices overdue', implode(' ', $labels));
        $this->assertStringContainsString('Jobs awaiting approval', implode(' ', $labels));
        $this->assertStringContainsString('Quotations awaiting a customer decision', implode(' ', $labels));
        $this->assertContains(1, $counts);

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

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role, 'is_active' => true]);

        return $user;
    }
}
