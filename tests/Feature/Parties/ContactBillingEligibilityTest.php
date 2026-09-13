<?php

namespace Tests\Feature\Parties;

use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 02 required test (docs/rebuild/specs/02-parties-and-catalog/Specs.md):
 * "Contact portal eligibility is scoped to its client/company." Per
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §5, only a client's designated
 * billing contact(s) see that client's full billing history in the
 * portal — this asserts the designation (`contacts.is_billing_contact`)
 * never leaks across clients or companies when queried the way a future
 * portal-access check would query it.
 */
class ContactBillingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_contact_designation_is_scoped_to_its_own_client(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);

        $clientA = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $clientB = Client::create(['company_id' => $company->id, 'name' => 'Client B']);

        $billingContact = Contact::create([
            'client_id' => $clientA->id,
            'first_name' => 'Billing',
            'last_name' => 'Contact',
            'is_billing_contact' => true,
        ]);
        $ordinaryContact = Contact::create([
            'client_id' => $clientA->id,
            'first_name' => 'Ordinary',
            'last_name' => 'Contact',
        ]);
        Contact::create([
            'client_id' => $clientB->id,
            'first_name' => 'Other Client',
            'last_name' => 'Billing Contact',
            'is_billing_contact' => true,
        ]);

        $billingContactsForClientA = $clientA->contacts()->where('is_billing_contact', true)->get();

        $this->assertCount(1, $billingContactsForClientA);
        $this->assertTrue($billingContactsForClientA->first()->is($billingContact));
        $this->assertFalse($ordinaryContact->fresh()->is_billing_contact);
    }

    public function test_billing_contact_designation_never_leaks_across_companies(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'currency_code' => 'USD']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b', 'currency_code' => 'USD']);

        $clientA = Client::create(['company_id' => $companyA->id, 'name' => 'Same Name Client']);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Same Name Client']);

        $contactA = Contact::create([
            'client_id' => $clientA->id,
            'first_name' => 'Jane',
            'is_billing_contact' => true,
        ]);
        Contact::create([
            'client_id' => $clientB->id,
            'first_name' => 'Jane',
            'is_billing_contact' => false,
        ]);

        // Same first name, same designation intent checked per-client — a
        // query scoped to Company A's client must never surface Company
        // B's contact just because the name matches.
        $this->assertSame(1, Contact::query()
            ->whereHas('client', fn ($q) => $q->where('company_id', $companyA->id))
            ->where('is_billing_contact', true)
            ->count());

        $this->assertTrue($contactA->fresh()->is_billing_contact);
    }
}
