<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\ViewInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public "view my invoice" page `invitations.key` resolves to — closes
 * the gap flagged in docs/filament-admin-layout-design.md §2.2/§3.5.
 */
class ViewInvoicePortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvitation(Company $company, array $invoiceAttributes = []): Invitation
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_primary' => true]);
        $invoice = Invoice::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
        ], $invoiceAttributes));
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Consulting',
            'quantity' => 2,
            'unit_cost' => 100,
            'line_total' => 200,
        ]);
        $invoice->forceFill(['subtotal' => 200, 'total' => 200, 'balance' => 200])->save();

        return Invitation::create(['invoice_id' => $invoice->id, 'contact_id' => $contact->id]);
    }

    public function test_portal_page_shows_the_invoice_for_the_domain_matched_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $invitation = $this->makeInvitation($company);

        $this->get("http://acme.test/portal/{$invitation->key}")
            ->assertOk()
            ->assertSee('INV-0001')
            ->assertSee('Consulting')
            ->assertSee('Client Co');
    }

    public function test_viewing_the_page_marks_the_invitation_viewed_and_bumps_a_sent_invoice_to_viewed(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $invitation = $this->makeInvitation($company, ['status' => 'sent']);

        $this->assertNull($invitation->viewed_at);

        $this->get("http://acme.test/portal/{$invitation->key}")->assertOk();

        $invitation->refresh();
        $this->assertNotNull($invitation->viewed_at);
        $this->assertSame('viewed', $invitation->invoice->fresh()->status->value);
    }

    public function test_portal_page_404s_when_the_invitation_belongs_to_a_different_company_than_the_resolved_domain(): void
    {
        $owner = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.test']);
        $invitation = $this->makeInvitation($owner);

        $this->get("http://{$other->domain}/portal/{$invitation->key}")->assertNotFound();
    }

    public function test_signing_records_the_signature_and_timestamp(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $invitation = $this->makeInvitation($company);

        // Normally bound by ResolveCompanyFromDomain; Livewire::test() mounts
        // the component directly, bypassing route middleware (see
        // HomePageTest for the same pattern).
        $this->app->instance('currentCompany', $company);

        Livewire::test(ViewInvoice::class, ['invitation' => $invitation])
            ->set('signatureName', 'Jane Doe')
            ->call('sign')
            ->assertSet('justSigned', true);

        $invitation->refresh();
        $this->assertSame('Jane Doe', $invitation->signature);
        $this->assertNotNull($invitation->signed_at);
    }
}
