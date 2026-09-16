<?php

namespace Tests\Feature\TallStack;

use App\Enums\ProposalStatus;
use App\Livewire\TallStackProposals;
use App\Models\Client;
use App\Models\Company;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers wiring the TallStackUI <x-table>'s built-in sort/quantity onto
 * this register. Proposal.client_id is nullable, so render() must use a
 * leftJoin (not an inner join) on clients — this also proves a clientless
 * proposal still appears in the paginated result rather than being
 * silently dropped. Sorting by the joined "client" column must not throw
 * an ambiguous-column SQL error, and the quantity filter must actually
 * cap the page size.
 */
class TallStackProposalsSortAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->actingAs($this->user);
    }

    private function proposalForClient(string $title, ?string $clientName): Proposal
    {
        $clientId = $clientName
            ? Client::create(['company_id' => $this->company->id, 'name' => $clientName])->id
            : null;

        return Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $clientId,
            'title' => $title,
            'status' => ProposalStatus::Draft,
            'amount' => 1000,
        ]);
    }

    public function test_sorting_by_client_orders_by_the_joined_name_and_quantity_caps_the_page(): void
    {
        $this->proposalForClient('Zeta proposal', 'Zeta Client');
        $this->proposalForClient('Alpha proposal', 'Alpha Client');
        $this->proposalForClient('Clientless proposal', null);

        $component = Livewire::test(TallStackProposals::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->assertHasNoErrors();

        // A leftJoin keeps the clientless row (client => '—', sorted
        // first/last per the DB's own NULL ordering) instead of an inner
        // join silently dropping it from the page.
        $rows = collect($component->viewData('proposals')->items());
        $this->assertCount(3, $rows);
        $this->assertContains('—', $rows->pluck('client')->all());

        $named = $rows->pluck('client')->reject(fn ($name) => $name === '—')->values()->all();
        $this->assertSame(['Alpha Client', 'Zeta Client'], $named);

        $limited = Livewire::test(TallStackProposals::class, ['company' => $this->company])
            ->set('quantity', 2)
            ->assertHasNoErrors();

        $this->assertCount(2, $limited->viewData('proposals')->items());
        $this->assertSame(3, $limited->viewData('proposals')->total());
    }
}
