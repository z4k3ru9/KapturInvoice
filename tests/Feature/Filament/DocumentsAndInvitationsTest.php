<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Invitations\InvitationResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsAndInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->otherCompany->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_document_resource_index_page_renders(): void
    {
        $this->get(DocumentResource::getUrl('index', tenant: $this->company))->assertOk();
    }

    public function test_invitation_resource_only_shows_the_current_tenants_invitations(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Own Client']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane']);
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'draft']);
        Invitation::create(['invoice_id' => $invoice->id, 'contact_id' => $contact->id]);

        Filament::setTenant($this->otherCompany);
        $otherClient = Client::create(['company_id' => $this->otherCompany->id, 'name' => 'Other Client']);
        $otherContact = Contact::create(['client_id' => $otherClient->id, 'first_name' => 'Jack']);
        $otherInvoice = Invoice::create(['company_id' => $this->otherCompany->id, 'client_id' => $otherClient->id, 'type' => 'invoice', 'status' => 'draft']);
        Invitation::create(['invoice_id' => $otherInvoice->id, 'contact_id' => $otherContact->id]);
        Filament::setTenant($this->company);

        $this->get(InvitationResource::getUrl('index', tenant: $this->company))
            ->assertOk()
            ->assertSee('Jane')
            ->assertDontSee('Jack');
    }
}
