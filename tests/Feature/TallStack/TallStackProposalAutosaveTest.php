<?php

namespace Tests\Feature\TallStack;

use App\Enums\ProposalStatus;
use App\Livewire\TallStackProposalForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proposal was the one document edit form still without
 * App\Livewire\Concerns\AutosavesDraft (see App\Livewire\
 * TallStackInvoiceForm's own equivalent test for the full reference
 * coverage this ports the core guarantees from — draft-only, version-
 * advancing, conflict-aware, role-gated).
 */
class TallStackProposalAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);

        return $user;
    }

    private function draftProposal(): Proposal
    {
        return Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'title' => 'Original title',
            'status' => ProposalStatus::Draft,
            'amount' => 1000,
        ]);
    }

    public function test_autosave_persists_a_draft_field_and_advances_the_version(): void
    {
        $this->actingAsRole('owner');
        $proposal = $this->draftProposal();

        // Field change -> updatedTitle() -> autosaveDraft(), the real
        // end-to-end wiring, not a raw method call.
        $component = Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('title', 'Revised title');

        $component->assertSet('autosaveStatus', 'saved');

        $fresh = $proposal->fresh();
        $this->assertSame('Revised title', $fresh->title);
        $this->assertSame(1, $fresh->draft_version);
    }

    public function test_autosave_persists_html_content(): void
    {
        $this->actingAsRole('owner');
        $proposal = $this->draftProposal();

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('html', '<p>Scope of work</p>')
            ->assertSet('autosaveStatus', 'saved');

        $this->assertSame('<p>Scope of work</p>', $proposal->fresh()->html);
    }

    public function test_a_stale_autosave_is_rejected_as_a_conflict_not_silently_merged(): void
    {
        $this->actingAsRole('owner');
        $proposal = $this->draftProposal();
        $proposal->forceFill(['title' => 'Someone else typed this', 'draft_version' => 1])->save();

        $component = Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('autosaveKnownVersion', 0)
            ->set('title', 'My own edit')
            ->call('autosaveDraft');

        $component->assertSet('autosaveStatus', 'conflict');

        $this->assertSame('Someone else typed this', $proposal->fresh()->title);
        $this->assertArrayHasKey('title', $component->get('autosaveConflictFields'));
    }

    public function test_autosave_never_runs_once_the_proposal_leaves_draft_status(): void
    {
        $this->actingAsRole('owner');
        $proposal = $this->draftProposal();
        $proposal->update(['status' => ProposalStatus::Sent]);

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('title', 'Should not autosave')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertSame('Original title', $proposal->fresh()->title);
    }

    public function test_autosave_never_touches_status_or_amount(): void
    {
        $this->actingAsRole('owner');
        $proposal = $this->draftProposal();

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('title', 'Just the title')
            ->assertSet('autosaveStatus', 'saved');

        $fresh = $proposal->fresh();
        $this->assertSame(ProposalStatus::Draft, $fresh->status);
        $this->assertSame(1000.0, (float) $fresh->amount);
    }

    public function test_autosave_never_runs_for_a_read_only_role(): void
    {
        $this->actingAsRole('auditor');
        $proposal = $this->draftProposal();

        Livewire::test(TallStackProposalForm::class, ['company' => $this->company, 'proposal' => $proposal])
            ->set('title', 'Auditor should not be able to save this')
            ->assertSet('autosaveStatus', 'idle');

        $this->assertSame('Original title', $proposal->fresh()->title);
    }
}
