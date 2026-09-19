<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackClientPortalInvitations;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the TALL-stack Client Portal Invitations register
 * (App\Livewire\TallStackClientPortalInvitations) — same shape as the
 * pre-TallStackUI legacy admin admin's equivalent invitation coverage,
 * proving this read-mostly register scopes correctly (Invitation
 * has no company_id of its own — scoped only via its invoice), and that
 * search/Viewed/Signed filters and the "Copy portal link" action are all
 * present.
 */
class TallStackClientPortalInvitationsTest extends TestCase
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

    private function makeInvitation(Company $company, array $invitationAttributes = [], array $invoiceAttributes = []): Invitation
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $contact = Contact::create(array_merge([
            'client_id' => $client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ], []));
        $invoice = Invoice::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0001',
        ], $invoiceAttributes));

        return Invitation::create(array_merge([
            'invoice_id' => $invoice->id,
            'contact_id' => $contact->id,
        ], $invitationAttributes));
    }

    public function test_page_loads(): void
    {
        Livewire::test(TallStackClientPortalInvitations::class, ['company' => $this->company])
            ->assertOk();
    }

    public function test_list_shows_invitations_scoped_to_the_tenant_only(): void
    {
        $this->makeInvitation($this->company, [], ['number' => 'INV-0001']);

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $this->makeInvitation($otherCompany, [], ['number' => 'INV-9999']);

        Livewire::test(TallStackClientPortalInvitations::class, ['company' => $this->company])
            ->assertSee('INV-0001')
            ->assertDontSee('INV-9999');
    }

    public function test_search_filters_by_invoice_number_and_contact(): void
    {
        $this->makeInvitation($this->company, [], ['number' => 'INV-0001']);

        Livewire::test(TallStackClientPortalInvitations::class, ['company' => $this->company])
            ->set('search', 'INV-0001')
            ->assertSee('INV-0001')
            ->set('search', 'Jane')
            ->assertSee('INV-0001')
            ->set('search', 'nomatch')
            ->assertDontSee('INV-0001');
    }

    public function test_viewed_and_signed_filters_narrow_the_list(): void
    {
        $this->makeInvitation($this->company, ['viewed_at' => now(), 'signed_at' => now()], ['number' => 'INV-VIEWED-SIGNED']);
        $this->makeInvitation($this->company, [], ['number' => 'INV-UNSEEN']);

        Livewire::test(TallStackClientPortalInvitations::class, ['company' => $this->company])
            ->call('filterBy', 'viewed')
            ->assertSee('INV-VIEWED-SIGNED')
            ->assertDontSee('INV-UNSEEN')
            ->call('filterBy', 'signed')
            ->assertSee('INV-VIEWED-SIGNED')
            ->assertDontSee('INV-UNSEEN')
            ->call('filterBy', null)
            ->assertSee('INV-VIEWED-SIGNED')
            ->assertSee('INV-UNSEEN');
    }

    public function test_copy_portal_link_action_is_present(): void
    {
        $invitation = $this->makeInvitation($this->company, [], ['number' => 'INV-0001']);

        Livewire::test(TallStackClientPortalInvitations::class, ['company' => $this->company])
            ->assertSee('Copy portal link')
            ->assertSee($invitation->key);
    }

    public function test_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        Livewire::test(TallStackClientPortalInvitations::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }
}
