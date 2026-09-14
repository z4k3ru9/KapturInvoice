<?php

namespace Tests\Feature\Console;

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * App\Console\Commands\ExpireQuotations — closes the "no automatic
 * quotation expiry" gap from
 * docs/rebuild/outputs/17-phase-03-checkpoint-report.md.
 */
class ExpireQuotationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(Company $company, Client $client, QuotationStatus $status, ?string $validUntil): Quotation
    {
        return Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-'.uniqid(),
            'status' => $status,
            'valid_until' => $validUntil,
        ]);
    }

    public function test_command_expires_a_sent_quotation_past_its_valid_until_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $quotation = $this->makeQuotation($company, $client, QuotationStatus::Sent, now()->subDay()->toDateString());

        Artisan::call('quotations:expire');

        $this->assertTrue($quotation->fresh()->status === QuotationStatus::Expired);
        $this->assertNotNull($quotation->fresh()->expired_at);
    }

    public function test_command_skips_a_sent_quotation_still_within_its_valid_until_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $quotation = $this->makeQuotation($company, $client, QuotationStatus::Sent, now()->addDay()->toDateString());

        Artisan::call('quotations:expire');

        $this->assertTrue($quotation->fresh()->status === QuotationStatus::Sent);
    }

    public function test_command_skips_a_draft_quotation_past_its_valid_until_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $quotation = $this->makeQuotation($company, $client, QuotationStatus::Draft, now()->subDay()->toDateString());

        Artisan::call('quotations:expire');

        $this->assertTrue($quotation->fresh()->status === QuotationStatus::Draft);
    }

    public function test_command_skips_a_sent_quotation_with_no_valid_until_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $quotation = $this->makeQuotation($company, $client, QuotationStatus::Sent, null);

        Artisan::call('quotations:expire');

        $this->assertTrue($quotation->fresh()->status === QuotationStatus::Sent);
    }
}
