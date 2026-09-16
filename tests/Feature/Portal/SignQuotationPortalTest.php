<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\SignQuotation;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public "review and accept my quotation" page
 * `quotations.portal_key` resolves to — mirrors ViewInvoicePortalTest's
 * coverage shape for the same drawn-signature capability extended to
 * Quotation approval, wired through the existing
 * App\Actions\Sales\AcceptQuotation action rather than bypassing it.
 */
class SignQuotationPortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(Company $company, array $attributes = []): Quotation
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'QUO-0001',
            'status' => 'sent',
            'quotation_date' => '2026-09-01',
            'valid_until' => '2026-10-01',
        ], $attributes));
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Access control install',
            'quantity' => 1,
            'unit_cost' => 5000,
            'line_total' => 5000,
        ]);
        $quotation->forceFill(['subtotal' => 5000, 'total' => 5000])->save();

        return $quotation;
    }

    public function test_portal_page_shows_the_quotation_for_the_domain_matched_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company);

        $this->assertNotNull($quotation->portal_key);

        $this->get("http://acme.test/portal/quotations/{$quotation->portal_key}")
            ->assertOk()
            ->assertSee('QUO-0001')
            ->assertSee('Access control install')
            ->assertSee('Client Co');
    }

    public function test_visiting_the_portal_page_records_viewed_at_once(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company);

        $this->assertNull($quotation->viewed_at);

        $this->get("http://acme.test/portal/quotations/{$quotation->portal_key}")->assertOk();

        $firstViewedAt = $quotation->fresh()->viewed_at;
        $this->assertNotNull($firstViewedAt);

        // A second visit doesn't move the timestamp forward.
        $this->travel(1)->hour();
        $this->get("http://acme.test/portal/quotations/{$quotation->portal_key}")->assertOk();

        $this->assertTrue($firstViewedAt->equalTo($quotation->fresh()->viewed_at));
    }

    public function test_portal_page_404s_when_the_quotation_belongs_to_a_different_company_than_the_resolved_domain(): void
    {
        $owner = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.test']);
        $quotation = $this->makeQuotation($owner);

        $this->get("http://{$other->domain}/portal/quotations/{$quotation->portal_key}")
            ->assertNotFound()
            ->assertSee('This link is no longer available')
            ->assertDontSee($quotation->number);
    }

    public function test_an_unknown_portal_key_renders_the_calm_unavailable_page_instead_of_a_bare_404(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);

        $this->get("http://{$company->domain}/portal/quotations/does-not-exist")
            ->assertNotFound()
            ->assertSee('This link is no longer available')
            ->assertSee('Acme');
    }

    public function test_accepting_signs_the_quotation_and_generates_a_customer_order_confirmation_when_no_po_supplied(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company);
        $this->app->instance('currentCompany', $company);

        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        Livewire::test(SignQuotation::class, ['quotation' => $quotation])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', $signature)
            ->call('accept')
            ->assertHasNoErrors()
            ->assertSet('justSigned', true);

        $quotation->refresh();
        $this->assertSame($signature, $quotation->signature);
        $this->assertSame('Jane Doe', $quotation->signed_by_name);
        $this->assertNotNull($quotation->signed_at);
        $this->assertSame('accepted', $quotation->status->value);
        $this->assertNotNull($quotation->accepted_at);
        $this->assertTrue($quotation->customer_po_is_system_generated);
        $this->assertNotNull($quotation->customer_po_number);
    }

    public function test_accepting_with_a_supplied_customer_po_records_it_instead_of_generating_a_confirmation(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company);
        $this->app->instance('currentCompany', $company);

        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        Livewire::test(SignQuotation::class, ['quotation' => $quotation])
            ->set('signerName', 'Jane Doe')
            ->set('customerPoNumber', 'PO-99887')
            ->set('capturedSignature', $signature)
            ->call('accept')
            ->assertHasNoErrors();

        $quotation->refresh();
        $this->assertFalse($quotation->customer_po_is_system_generated);
        $this->assertSame('PO-99887', $quotation->customer_po_number);
    }

    public function test_accepting_without_a_drawn_signature_is_rejected_and_leaves_the_quotation_unaccepted(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company);
        $this->app->instance('currentCompany', $company);

        Livewire::test(SignQuotation::class, ['quotation' => $quotation])
            ->set('signerName', 'Jane Doe')
            ->call('accept')
            ->assertHasErrors(['capturedSignature' => 'required'])
            ->assertSet('justSigned', false);

        $quotation->refresh();
        $this->assertNull($quotation->signature);
        $this->assertSame('sent', $quotation->status->value);
        $this->assertNull($quotation->accepted_at);
    }

    public function test_accepting_with_a_non_image_value_is_rejected(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company);
        $this->app->instance('currentCompany', $company);

        Livewire::test(SignQuotation::class, ['quotation' => $quotation])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', 'not an image')
            ->call('accept')
            ->assertHasErrors(['capturedSignature' => 'starts_with'])
            ->assertSet('justSigned', false);

        $quotation->refresh();
        $this->assertNull($quotation->signature);
        $this->assertSame('sent', $quotation->status->value);
    }

    public function test_a_draft_quotation_cannot_be_accepted_from_the_portal(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company, ['status' => 'draft']);
        $this->app->instance('currentCompany', $company);

        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        Livewire::test(SignQuotation::class, ['quotation' => $quotation])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', $signature)
            ->call('accept')
            ->assertHasErrors('capturedSignature');

        $quotation->refresh();
        $this->assertNull($quotation->signature);
        $this->assertSame('draft', $quotation->status->value);
    }

    public function test_a_signed_quotation_renders_the_signature_image_and_status(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $quotation = $this->makeQuotation($company, [
            'status' => 'accepted',
            'accepted_at' => now(),
            'customer_po_number' => 'PO-1',
            'customer_po_is_system_generated' => false,
        ]);
        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');
        $quotation->forceFill(['signature' => $signature, 'signed_by_name' => 'Jane Doe', 'signed_at' => now()])->save();

        $response = $this->get("http://acme.test/portal/quotations/{$quotation->portal_key}")->assertOk();

        $response->assertSee('src="'.$signature.'"', false);
        $response->assertSee('Jane Doe');
        $response->assertSee('PO-1');
    }
}
