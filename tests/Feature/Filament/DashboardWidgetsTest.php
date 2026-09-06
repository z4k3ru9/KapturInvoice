<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ExpiringQuotesWidget;
use App\Filament\Widgets\RevenueOverview;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The post-login dashboard — see docs/filament-admin-layout-design.md §9.
 * Replaces Filament's stock "framework info" dashboard with real stats, a
 * revenue trend chart, and an upcoming/expired-quotes list.
 */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_page_renders(): void
    {
        $this->get(Dashboard::getUrl(tenant: $this->company))->assertOk();
    }

    public function test_revenue_overview_computes_this_months_revenue_and_pending_and_overdue_counts(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'paid', 'number' => 'INV-0001']);
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id, 'invoice_id' => $invoice->id,
            'amount' => 150, 'status' => 'completed', 'payment_date' => '2026-09-10',
        ]);
        // Outside this month — must not count toward "this month" revenue.
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'amount' => 999, 'status' => 'completed', 'payment_date' => '2026-08-01',
        ]);

        $pending = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0002', 'due_date' => '2026-09-30',
        ]);
        $pending->forceFill(['balance' => 200])->save();

        $overdue = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0003', 'due_date' => '2026-09-01',
        ]);
        $overdue->forceFill(['balance' => 75])->save();

        Livewire::test(RevenueOverview::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Total revenue')
            ->assertSee('150.00')
            ->assertDontSee('999.00')
            ->assertSee('Pending invoices')
            ->assertSee('200.00')
            ->assertSee('Overdue invoices')
            ->assertSee('75.00');
    }

    public function test_expiring_quotes_widget_lists_quotes_due_within_14_days_or_already_past_and_not_yet_converted(): void
    {
        $expiringSoon = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'quote', 'status' => 'sent', 'number' => 'QUO-0001', 'due_date' => '2026-09-20',
        ]);
        $alreadyExpired = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'quote', 'status' => 'sent', 'number' => 'QUO-0002', 'due_date' => '2026-09-01',
        ]);
        $farOut = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'quote', 'status' => 'sent', 'number' => 'QUO-0003', 'due_date' => '2026-12-01',
        ]);
        $alreadyConverted = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'quote', 'status' => 'sent', 'number' => 'QUO-0004', 'due_date' => '2026-09-18',
        ]);
        Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'draft', 'number' => 'INV-CONV', 'converted_from_quote_id' => $alreadyConverted->id,
        ]);

        Livewire::test(ExpiringQuotesWidget::class)
            ->assertCanSeeTableRecords([$expiringSoon, $alreadyExpired])
            ->assertCanNotSeeTableRecords([$farOut, $alreadyConverted]);
    }
}
