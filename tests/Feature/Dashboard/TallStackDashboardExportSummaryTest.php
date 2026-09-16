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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Covers the Dashboard's "Export summary" header button — previously
 * decorative with no `wire:click` at all (see memory.md's Current state).
 * App\Livewire\TallStackDashboard::exportSummary() streams a CSV of the
 * current period's stat cards and revenue trend.
 */
class TallStackDashboardExportSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $this->owner = User::factory()->create();
        $this->company->users()->attach($this->owner, ['role' => 'owner', 'is_active' => true]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $client->id,
            'type' => InvoiceType::Invoice, 'status' => InvoiceStatus::Sent, 'number' => 'INV-0001',
            'due_date' => now()->addDays(10),
        ]);
        $invoice->forceFill(['balance' => 250])->save();

        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $client->id,
            'status' => PaymentStatus::Verified, 'amount' => 500, 'payment_date' => now(),
        ]);
    }

    private function csvBody(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_export_streams_a_csv_with_the_current_period_summary(): void
    {
        $this->actingAs($this->owner);

        $component = Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->call('loadDashboardData');

        $response = $component->instance()->exportSummary();

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $csv = $this->csvBody($response);

        $this->assertStringContainsString('Acme', $csv);
        $this->assertStringContainsString('Total revenue', $csv);
        $this->assertStringContainsString('Outstanding balance', $csv);
        $this->assertStringContainsString('Revenue & cash inflow trend', $csv);
    }

    public function test_export_loads_stats_itself_even_if_never_loaded_yet(): void
    {
        $this->actingAs($this->owner);

        // No ->call('loadDashboardData') first — the header button sits
        // above the wire:init block, reachable before that round trip
        // fires (see the method's own docblock).
        $component = Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->assertSet('statsLoaded', false);

        $response = $component->instance()->exportSummary();

        $csv = $this->csvBody($response);

        // The seeded $500 verified payment, proving real data was
        // computed rather than an empty $this->stats array being exported.
        $this->assertStringContainsString('500', $csv);
    }
}
