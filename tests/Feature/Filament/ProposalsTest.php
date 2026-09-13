<?php

namespace Tests\Feature\Filament;

use App\Enums\ProposalStatus;
use App\Filament\Resources\Proposals\Pages\ListProposals;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\ProposalSnippets\ProposalSnippetResource;
use App\Filament\Resources\ProposalTemplates\ProposalTemplateResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Proposal;
use App\Models\ProposalTemplate;
use App\Models\User;
use App\Services\ProposalConverter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Proposals module — see docs/filament-admin-layout-design.md §2.7.
 * Kept in its own nav group (Proposals/Proposal Templates/Proposal
 * Snippets) rather than bolted onto Invoices/Quotes.
 */
class ProposalsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD', 'invoice_prefix' => 'INV-', 'invoice_next_number' => 1]);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_resource_index_pages_render(): void
    {
        // None of the three keeps a dedicated Create page anymore — all
        // three open via a modal instead, covered by ModalCreateEditTest.
        // See docs/filament-admin-layout-design.md §6.
        $this->get(ProposalResource::getUrl('index', tenant: $this->company))->assertOk();
        $this->get(ProposalTemplateResource::getUrl('index', tenant: $this->company))->assertOk();
        $this->get(ProposalSnippetResource::getUrl('index', tenant: $this->company))->assertOk();
    }

    public function test_creating_a_proposal_from_a_template_copies_its_content(): void
    {
        $template = ProposalTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Standard SOW',
            'html' => '<p>Scope of work…</p>',
            'css' => 'p { color: navy; }',
        ]);

        Livewire::test(ListProposals::class)
            ->mountAction('create')
            ->setActionData(['title' => 'Website redesign', 'proposal_template_id' => $template->id])
            ->assertActionDataSet(['html' => '<p>Scope of work…</p>', 'css' => 'p { color: navy; }'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('proposals', [
            'company_id' => $this->company->id,
            'title' => 'Website redesign',
            'proposal_template_id' => $template->id,
        ]);
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

    public function test_convert_to_invoice_table_action_is_hidden_once_converted(): void
    {
        $proposal = Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'title' => 'Website redesign',
            'status' => 'accepted',
            'amount' => 500,
        ]);

        Livewire::test(ListProposals::class)
            ->assertTableActionVisible('convertToInvoice', $proposal)
            ->callTableAction('convertToInvoice', $proposal);

        Livewire::test(ListProposals::class)
            ->assertTableActionHidden('convertToInvoice', $proposal->fresh());
    }

    public function test_mark_accepted_and_declined_actions_update_status(): void
    {
        $proposal = Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'title' => 'Website redesign',
            'status' => 'sent',
            'amount' => 500,
        ]);

        Livewire::test(ListProposals::class)->callTableAction('markAccepted', $proposal);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);

        Livewire::test(ListProposals::class)->callTableAction('markDeclined', $proposal);
        $this->assertSame(ProposalStatus::Declined, $proposal->fresh()->status);
    }
}
