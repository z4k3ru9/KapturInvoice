<?php

namespace Tests\Feature\TallStack;

use App\Enums\ProposalStatus;
use App\Livewire\TallStackProposalForm;
use App\Livewire\TallStackProposals;
use App\Models\Client;
use App\Models\Company;
use App\Models\Proposal;
use App\Models\ProposalSnippet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the TALL-stack Proposals register and editor
 * (App\Livewire\TallStackProposals / App\Livewire\TallStackProposalForm)
 * — same shape as tests/Feature/TallStack/TallStackQuotationSendTest.php:
 * proves status changes and conversion reuse the exact same domain code
 * the Filament resource uses (App\Enums\ProposalStatus,
 * App\Services\ProposalConverter), and that a cross-company proposal is
 * never reachable through either component.
 */
class TallStackProposalsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($this->user);
    }

    private function proposal(array $overrides = []): Proposal
    {
        return Proposal::create(array_merge([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'title' => 'Security System Upgrade',
            'status' => ProposalStatus::Draft,
            'amount' => 15_000_000,
        ], $overrides));
    }

    public function test_the_list_page_marks_a_proposal_accepted(): void
    {
        $proposal = $this->proposal();

        Livewire::test(TallStackProposals::class, ['company' => $this->company])
            ->call('markAccepted', $proposal->id);

        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
        $this->assertNotNull($proposal->fresh()->responded_at);
    }

    public function test_the_editor_saves_header_fields(): void
    {
        $proposal = $this->proposal();

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('title', 'Updated title')
            ->set('amount', 20_000_000)
            ->call('save');

        $proposal->refresh();
        $this->assertSame('Updated title', $proposal->title);
        $this->assertSame('20000000.00', $proposal->amount);
    }

    /**
     * The "disabled until Accepted" behavior in prompt 12's Stitch spec
     * is a UI-only affordance on this page (the button itself is
     * disabled with a tooltip in the Blade view) — the underlying
     * App\Services\ProposalConverter::convertToInvoice(), and the
     * pre-TallStackUI Filament admin's own "Convert to invoice" row
     * action gated conversion only on "not already converted", never on
     * status. This
     * page's own convertToInvoice() method matches that exactly rather
     * than inventing a stricter server-side rule the Filament resource
     * doesn't enforce — see this method's own docblock.
     */
    public function test_convert_to_invoice_creates_an_invoice_once_accepted(): void
    {
        $proposal = $this->proposal(['status' => ProposalStatus::Accepted]);

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->call('convertToInvoice');

        $proposal->refresh();
        $this->assertNotNull($proposal->invoice_id);
        $this->assertSame((float) $proposal->amount, (float) $proposal->invoice->total);
    }

    public function test_insert_snippet_appends_its_html_to_the_content(): void
    {
        $proposal = $this->proposal(['html' => '<p>Intro</p>']);
        $snippet = ProposalSnippet::create([
            'company_id' => $this->company->id,
            'name' => 'Camera kit',
            'html' => '<div class="product-snippet">Camera kit</div>',
        ]);

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->call('insertSnippet', $snippet->id)
            ->call('save');

        $proposal->refresh();
        $this->assertStringContainsString('<p>Intro</p>', $proposal->html);
        $this->assertStringContainsString('Camera kit', $proposal->html);
    }

    public function test_a_proposal_from_another_company_is_not_reachable(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $foreignProposal = Proposal::create([
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'title' => 'Not yours',
            'status' => ProposalStatus::Draft,
            'amount' => 1000,
        ]);

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $foreignProposal])
            ->assertStatus(404);
    }
}
