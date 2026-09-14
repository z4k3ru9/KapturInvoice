<?php

namespace Tests\Feature\Portal;

use App\Actions\Portal\GeneratePortalLink;
use App\Actions\Portal\RevokePortalLink;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\PortalLinksRelationManager;
use App\Livewire\Portal\ClientPortalHome;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PortalLink;
use App\Models\Receipt;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The broader, contact-scoped "portal link" from
 * docs/rebuild/specs/06-documents-portal-reporting/Specs.md — separate
 * from the existing single-invoice `Invitation`/`ViewInvoice` covered by
 * ViewInvoicePortalTest.
 */
class ClientPortalHomeTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Company $company, Client $client, array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-'.uniqid(),
            'invoice_date' => now(),
            'subtotal' => 200,
            'total' => 200,
            'balance' => 200,
        ], $attributes));
    }

    public function test_a_billing_contacts_portal_link_shows_every_invoice_but_not_quotes_or_recurring_templates(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);

        $invoiceOne = $this->makeInvoice($company, $client, ['number' => 'INV-0001']);
        $invoiceTwo = $this->makeInvoice($company, $client, ['number' => 'INV-0002']);
        $quote = $this->makeInvoice($company, $client, ['type' => 'quote', 'number' => 'QUO-0001']);
        $recurringTemplate = $this->makeInvoice($company, $client, ['number' => 'INV-TPL', 'is_recurring' => true]);

        $link = PortalLink::create(['company_id' => $company->id, 'client_id' => $client->id, 'contact_id' => $contact->id]);

        $this->app->instance('currentCompany', $company);

        $component = Livewire::test(ClientPortalHome::class, ['portalLink' => $link]);

        $invoiceNumbers = $component->get('invoices')->pluck('number');

        $this->assertTrue($invoiceNumbers->contains('INV-0001'));
        $this->assertTrue($invoiceNumbers->contains('INV-0002'));
        $this->assertFalse($invoiceNumbers->contains('QUO-0001'));
        $this->assertFalse($invoiceNumbers->contains('INV-TPL'));
    }

    public function test_an_ordinary_contacts_portal_link_shows_only_invoices_explicitly_shared_via_an_invitation(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Bob', 'email' => 'bob@example.com', 'is_billing_contact' => false]);

        $sharedInvoice = $this->makeInvoice($company, $client, ['number' => 'INV-SHARED']);
        $unsharedInvoice = $this->makeInvoice($company, $client, ['number' => 'INV-UNSHARED']);

        Invitation::create(['invoice_id' => $sharedInvoice->id, 'contact_id' => $contact->id]);

        $link = PortalLink::create(['company_id' => $company->id, 'client_id' => $client->id, 'contact_id' => $contact->id]);

        $this->app->instance('currentCompany', $company);

        $component = Livewire::test(ClientPortalHome::class, ['portalLink' => $link]);

        $invoiceNumbers = $component->get('invoices')->pluck('number');

        $this->assertTrue($invoiceNumbers->contains('INV-SHARED'));
        $this->assertFalse($invoiceNumbers->contains('INV-UNSHARED'));
    }

    public function test_portal_link_404s_when_opened_under_a_different_companys_resolved_domain(): void
    {
        $owner = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.test']);
        $client = Client::create(['company_id' => $owner->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);

        $link = PortalLink::create(['company_id' => $owner->id, 'client_id' => $client->id, 'contact_id' => $contact->id]);

        $this->get("http://{$other->domain}/portal/link/{$link->key}")->assertNotFound();
    }

    public function test_a_portal_link_never_leaks_a_different_clients_invoices_within_the_same_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $clientA = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $clientB = Client::create(['company_id' => $company->id, 'name' => 'Client B']);
        $contactA = Contact::create(['client_id' => $clientA->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);

        $this->makeInvoice($company, $clientA, ['number' => 'INV-A']);
        $this->makeInvoice($company, $clientB, ['number' => 'INV-B']);

        $link = PortalLink::create(['company_id' => $company->id, 'client_id' => $clientA->id, 'contact_id' => $contactA->id]);

        $this->app->instance('currentCompany', $company);

        $component = Livewire::test(ClientPortalHome::class, ['portalLink' => $link]);

        $invoiceNumbers = $component->get('invoices')->pluck('number');

        $this->assertTrue($invoiceNumbers->contains('INV-A'));
        $this->assertFalse($invoiceNumbers->contains('INV-B'));
    }

    public function test_a_revoked_portal_link_404s(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $link = PortalLink::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'revoked_at' => now(),
        ]);

        $this->get("http://{$company->domain}/portal/link/{$link->key}")->assertNotFound();
    }

    public function test_an_expired_portal_link_404s(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $link = PortalLink::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->get("http://{$company->domain}/portal/link/{$link->key}")->assertNotFound();
    }

    public function test_a_payment_recorded_through_the_allocation_based_workflow_shows_on_the_portal(): void
    {
        // Codex review finding on PR #4: App\Actions\Receivables\
        // RecordCustomerPayment leaves `payments.invoice_id` null and
        // links invoices only through `payment_allocations` — the portal
        // used to eager-load only the legacy `Invoice::payments()`
        // relation, so this payment (and its receipt) never appeared.
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $invoice = $this->makeInvoice($company, $client, ['number' => 'INV-ALLOC']);

        $payment = Payment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'method' => 'bank_transfer',
            'amount' => 150,
            'status' => 'verified',
        ]);
        PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 150, 'is_active' => true]);
        $receipt = Receipt::create(['company_id' => $company->id, 'payment_id' => $payment->id, 'number' => 'ACM-RCT-0001', 'issued_at' => now()]);

        $link = PortalLink::create(['company_id' => $company->id, 'client_id' => $client->id, 'contact_id' => $contact->id]);

        $this->get("http://{$company->domain}/portal/link/{$link->key}")
            ->assertOk()
            ->assertSee('150.00')
            ->assertSee($receipt->number);
    }

    public function test_payment_events_for_excludes_a_reversed_allocations_payment_but_keeps_a_legacy_direct_one(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $invoice = $this->makeInvoice($company, $client, ['number' => 'INV-MIX']);

        // Legacy-imported: direct FK, no allocation row.
        $legacy = Payment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 50,
            'status' => 'completed',
        ]);

        // New workflow: allocation-based, but reversed — inactive.
        $reversed = Payment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'method' => 'bank_transfer',
            'amount' => 75,
            'status' => 'reversed',
        ]);
        PaymentAllocation::create(['payment_id' => $reversed->id, 'invoice_id' => $invoice->id, 'amount' => 75, 'is_active' => false]);

        $link = PortalLink::create(['company_id' => $company->id, 'client_id' => $client->id, 'contact_id' => $contact->id]);
        $this->app->instance('currentCompany', $company);

        $component = Livewire::test(ClientPortalHome::class, ['portalLink' => $link]);
        $loadedInvoice = $component->get('invoices')->firstWhere('number', 'INV-MIX');

        $events = $component->instance()->paymentEventsFor($loadedInvoice);

        $this->assertCount(1, $events);
        $this->assertSame(50.0, $events[0]['amount']);
    }

    public function test_an_active_portal_link_works_under_its_own_companys_domain(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $this->makeInvoice($company, $client, ['number' => 'INV-0001']);
        $link = PortalLink::create(['company_id' => $company->id, 'client_id' => $client->id, 'contact_id' => $contact->id]);

        $this->get("http://{$company->domain}/portal/link/{$link->key}")
            ->assertOk()
            ->assertSee('INV-0001')
            ->assertSee('Client Co');
    }

    public function test_generating_a_portal_link_via_the_filament_action_creates_a_usable_link(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);

        $this->actingAs($user);
        Filament::setTenant($company);

        $this->assertSame(0, PortalLink::query()->count());

        Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->callTableAction('generatePortalLink', $contact, data: [
                'expires_at' => now()->addDays(30)->toDateString(),
            ]);

        $this->assertSame(1, PortalLink::query()->where('contact_id', $contact->id)->count());
    }

    public function test_revoking_a_portal_link_via_the_filament_action_sets_revoked_at_and_the_link_then_404s(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $link = app(GeneratePortalLink::class)->generate($contact);

        $this->actingAs($user);
        Filament::setTenant($company);

        Livewire::test(PortalLinksRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->callTableAction('revoke', $link);

        $link->refresh();
        $this->assertNotNull($link->revoked_at);

        $this->get("http://{$company->domain}/portal/link/{$link->key}")->assertNotFound();
    }

    public function test_revoke_portal_link_action_throws_when_already_revoked(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_billing_contact' => true]);
        $link = app(GeneratePortalLink::class)->generate($contact);
        app(RevokePortalLink::class)->revoke($link, $user);

        $this->expectException(\RuntimeException::class);
        app(RevokePortalLink::class)->revoke($link, $user);
    }
}
