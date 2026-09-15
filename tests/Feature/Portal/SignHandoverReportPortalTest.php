<?php

namespace Tests\Feature\Portal;

use App\Livewire\Portal\SignHandoverReport;
use App\Models\Client;
use App\Models\Company;
use App\Models\HandoverReport;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public "sign my handover report" page
 * `handover_reports.portal_key` resolves to — mirrors
 * ViewInvoicePortalTest's coverage shape for the same drawn-signature
 * capability extended to Handover Reports.
 */
class SignHandoverReportPortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeHandoverReport(Company $company): HandoverReport
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'QUO-0001',
            'status' => 'accepted',
        ]);
        $job = SalesOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'JOB-0001',
        ]);

        return HandoverReport::create([
            'company_id' => $company->id,
            'sales_order_id' => $job->id,
            'number' => 'HO-0001',
            'handover_date' => '2026-09-10',
            'notes' => 'Installed and tested on site.',
        ]);
    }

    public function test_portal_page_shows_the_handover_report_for_the_domain_matched_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $handoverReport = $this->makeHandoverReport($company);

        $this->assertNotNull($handoverReport->portal_key);

        $this->get("http://acme.test/portal/handover-reports/{$handoverReport->portal_key}")
            ->assertOk()
            ->assertSee('HO-0001')
            ->assertSee('Installed and tested on site.');
    }

    public function test_portal_page_404s_when_the_handover_report_belongs_to_a_different_company_than_the_resolved_domain(): void
    {
        $owner = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.test']);
        $handoverReport = $this->makeHandoverReport($owner);

        $this->get("http://{$other->domain}/portal/handover-reports/{$handoverReport->portal_key}")
            ->assertNotFound()
            ->assertSee('This link is no longer available')
            ->assertDontSee($handoverReport->number);
    }

    public function test_an_unknown_portal_key_renders_the_calm_unavailable_page_instead_of_a_bare_404(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);

        $this->get("http://{$company->domain}/portal/handover-reports/does-not-exist")
            ->assertNotFound()
            ->assertSee('This link is no longer available')
            ->assertSee('Acme');
    }

    public function test_signing_records_the_drawn_signature_name_and_timestamp(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $handoverReport = $this->makeHandoverReport($company);
        $this->app->instance('currentCompany', $company);

        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        Livewire::test(SignHandoverReport::class, ['handoverReport' => $handoverReport])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', $signature)
            ->call('sign')
            ->assertHasNoErrors()
            ->assertSet('justSigned', true);

        $handoverReport->refresh();
        $this->assertSame($signature, $handoverReport->signature);
        $this->assertSame('Jane Doe', $handoverReport->signed_by_name);
        $this->assertNotNull($handoverReport->signed_at);
    }

    public function test_signing_without_a_drawn_signature_is_rejected(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $handoverReport = $this->makeHandoverReport($company);
        $this->app->instance('currentCompany', $company);

        Livewire::test(SignHandoverReport::class, ['handoverReport' => $handoverReport])
            ->set('signerName', 'Jane Doe')
            ->call('sign')
            ->assertHasErrors(['capturedSignature' => 'required'])
            ->assertSet('justSigned', false);

        $handoverReport->refresh();
        $this->assertNull($handoverReport->signature);
        $this->assertNull($handoverReport->signed_at);
    }

    public function test_signing_with_a_non_image_value_is_rejected(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $handoverReport = $this->makeHandoverReport($company);
        $this->app->instance('currentCompany', $company);

        Livewire::test(SignHandoverReport::class, ['handoverReport' => $handoverReport])
            ->set('signerName', 'Jane Doe')
            ->set('capturedSignature', 'not an image')
            ->call('sign')
            ->assertHasErrors(['capturedSignature' => 'starts_with'])
            ->assertSet('justSigned', false);

        $handoverReport->refresh();
        $this->assertNull($handoverReport->signature);
    }

    public function test_a_signed_handover_report_renders_the_signature_image(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $handoverReport = $this->makeHandoverReport($company);
        $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');
        $handoverReport->forceFill(['signature' => $signature, 'signed_by_name' => 'Jane Doe', 'signed_at' => now()])->save();

        $response = $this->get("http://acme.test/portal/handover-reports/{$handoverReport->portal_key}")->assertOk();

        $response->assertSee('src="'.$signature.'"', false);
        $response->assertSee('Jane Doe');
    }
}
