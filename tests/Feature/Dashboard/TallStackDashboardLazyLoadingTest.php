<?php

namespace Tests\Feature\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Livewire\TallStackDashboard;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the beautification pass's stats/chart lazy loading — see
 * App\Livewire\TallStackDashboard's own docblock. The point being tested
 * is specifically that the expensive stats/chart computation
 * (RevenueBuckets::forPeriod()'s per-bucket query loop) is deliberately
 * NOT run during mount()/the initial render(), only from the explicit
 * loadDashboardData() call the Blade view fires via wire:init — so the
 * first HTTP response is genuinely lighter, not just decorated with an
 * unused `skeleton` prop.
 */
class TallStackDashboardLazyLoadingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $this->owner = User::factory()->create();
        $this->company->users()->attach($this->owner, ['role' => 'owner', 'is_active' => true]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice, 'status' => InvoiceStatus::Sent, 'number' => 'INV-0001',
            'due_date' => now()->addDays(10),
        ]);
        $invoice->forceFill(['balance' => 250])->save();

        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => PaymentStatus::Verified, 'amount' => 500, 'payment_date' => now(),
        ]);
    }

    public function test_initial_mount_does_not_compute_stats_or_the_chart(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->assertSet('statsLoaded', false)
            ->assertSet('stats', [])
            ->assertSet('chartLabels', [])
            ->assertSet('chartInvoiced', [])
            ->assertSet('chartCollected', []);
    }

    public function test_the_initial_http_response_renders_skeleton_placeholders_not_real_numbers(): void
    {
        $this->actingAs($this->owner);

        // The real "Outstanding balance" figure the seeded invoice would
        // otherwise produce — asserting its ABSENCE from the initial
        // response is what proves the heavy query never ran synchronously.
        $response = $this->get(route('tallstack.dashboard', $this->company));

        $response->assertOk();
        $response->assertSee('aria-busy="true"', false);
        $response->assertDontSee('250', false);
    }

    public function test_load_dashboard_data_computes_real_stats_and_marks_loaded(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->call('loadDashboardData')
            ->assertSet('statsLoaded', true)
            ->assertSet('stats.outstandingCount', 1)
            ->assertSet('stats.outstanding', 'US$250');
    }

    public function test_changing_the_period_reloads_the_data(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->call('loadDashboardData')
            ->assertSet('statsLoaded', true)
            ->set('period', 'this_year')
            ->assertSet('statsLoaded', true)
            ->assertSet('stats.outstandingCount', 1);
    }

    /**
     * The chart's "View as accessible table" toggle (found dead/unwired
     * during the dark-mode/TallStackUI audit — no click handler at all)
     * now renders a real <x-table> alternative built from the exact same
     * $chartInvoiced/$chartCollected buckets as the chart, formatted with
     * the same App\Support\Dashboard\Money::format() the stat cards use —
     * asserting the formatted collected amount appears is what proves the
     * table is fed real bucket data, not an empty stub.
     */
    public function test_the_chart_has_a_populated_accessible_table_alternative(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->call('loadDashboardData')
            ->assertSee('View as accessible table')
            ->assertSee('dashboard-trend-table', false)
            ->assertSee('US$500', false);
    }
}
