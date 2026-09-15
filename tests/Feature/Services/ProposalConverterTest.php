<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Proposal;
use App\Services\ProposalConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ported from tests/Feature/Filament/ProposalsTest.php during the
 * Filament-removal Phase B — these two tests exercise
 * App\Services\ProposalConverter directly (no Filament UI involved).
 * tests/Feature/TallStack/TallStackProposalsTest.php covers the
 * TallStackUI "Convert to invoice" happy path via the Livewire component,
 * but not the "already converted" RuntimeException case asserted here, so
 * these are kept rather than dropped along with the rest of that file's
 * genuinely Filament-UI-only tests.
 */
class ProposalConverterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD', 'invoice_prefix' => 'INV-', 'invoice_next_number' => 1]);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
    }

    public function test_converting_an_accepted_proposal_creates_an_invoice(): void
    {
        $proposal = Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'title' => 'Website redesign',
            'status' => 'accepted',
            'amount' => 500,
        ]);

        $invoice = app(ProposalConverter::class)->convertToInvoice($proposal);

        // "ACME-INV-{yearmonth}0001" — see App\Services\DocumentNumberGenerator.
        $this->assertSame('ACME-INV-'.now()->format('Ym').'0001', $invoice->number);
        $this->assertSame('500.00', (string) $invoice->total);
        $this->assertSame($invoice->id, $proposal->fresh()->invoice_id);
    }

    public function test_converting_the_same_proposal_twice_is_rejected(): void
    {
        $proposal = Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'title' => 'Website redesign',
            'status' => 'accepted',
            'amount' => 500,
        ]);

        app(ProposalConverter::class)->convertToInvoice($proposal);

        $this->expectException(\RuntimeException::class);
        app(ProposalConverter::class)->convertToInvoice($proposal->fresh());
    }
}
