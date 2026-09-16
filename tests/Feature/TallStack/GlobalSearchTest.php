<?php

namespace Tests\Feature\TallStack;

use App\Livewire\GlobalSearch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the shell's global search (App\Livewire\GlobalSearch) — segmented
 * results by document type, matching by number/title or client name,
 * scoped to the current tenant.
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Searchable Client']);

        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);
    }

    public function test_it_shows_nothing_below_the_minimum_query_length(): void
    {
        Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'draft', 'number' => 'ACM-INV-0001',
        ]);

        Livewire::test(GlobalSearch::class, ['company' => $this->company])
            ->set('query', 'S')
            ->assertViewHas('sections', []);
    }

    public function test_it_finds_an_invoice_by_client_name_and_groups_it_under_invoices(): void
    {
        Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'draft', 'number' => 'ACM-INV-0001',
        ]);

        $sections = Livewire::test(GlobalSearch::class, ['company' => $this->company])
            ->set('query', 'Searchable')
            ->viewData('sections');

        $this->assertCount(1, $sections);
        $this->assertSame('Invoices', $sections[0]['label']);
        $this->assertSame('Searchable Client', $sections[0]['results']->first()['client']);
        $this->assertStringContainsString('ACM-INV-0001', $sections[0]['results']->first()['description']);
    }

    public function test_it_finds_a_quotation_by_number(): void
    {
        Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => 'draft', 'number' => 'ACM-QUO-9999',
        ]);

        $sections = Livewire::test(GlobalSearch::class, ['company' => $this->company])
            ->set('query', 'QUO-9999')
            ->viewData('sections');

        $this->assertCount(1, $sections);
        $this->assertSame('Quotations', $sections[0]['label']);
    }

    public function test_it_finds_a_proposal_by_title_since_proposals_have_no_document_number(): void
    {
        Proposal::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'title' => 'Unique Rooftop Install Proposal', 'status' => 'draft', 'amount' => 100,
        ]);

        $sections = Livewire::test(GlobalSearch::class, ['company' => $this->company])
            ->set('query', 'Rooftop')
            ->viewData('sections');

        $this->assertCount(1, $sections);
        $this->assertSame('Proposals', $sections[0]['label']);
        $this->assertStringContainsString('Unique Rooftop Install Proposal', $sections[0]['results']->first()['description']);
    }

    public function test_it_never_returns_another_companys_records(): void
    {
        $other = Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $other->id, 'name' => 'Other Client']);
        Invoice::create([
            'company_id' => $other->id, 'client_id' => $otherClient->id,
            'type' => 'invoice', 'status' => 'draft', 'number' => 'OTH-INV-0001',
        ]);

        // mount() sets Tenancy to $this->company (Acme) itself — proves
        // the "Other Co" row is genuinely excluded by the tenant scope,
        // not just absent because Tenancy happened to be unset.
        $sections = Livewire::test(GlobalSearch::class, ['company' => $this->company])
            ->set('query', 'Other')
            ->viewData('sections');

        $this->assertSame([], $sections);
    }
}
