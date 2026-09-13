<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\AmendIssuedInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\VoidAndReissueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyTaxSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/04-billing-and-receivables/Specs.md:
 * "Rp10,000,000 exclusive example", "Rp11,100,000 inclusive example",
 * "Non-tax company behavior", "Line/global percentage/nominal discounts",
 * "Ceiling rounding", "Original invoice snapshot/PDF retained after
 * correction" — plus the invalid-transition and void-and-reissue coverage.
 */
class InvoiceIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function axenCompany(): Company
    {
        $company = Company::create([
            'name' => 'Axen Technology Indonesia',
            'slug' => 'axen-technology-indonesia',
            'code' => 'ATI',
            'currency_code' => 'IDR',
        ]);

        CompanyTaxSetting::create([
            'company_id' => $company->id,
            'tax_enabled' => true,
            'standard_tax_rate' => 12.00,
            'dpp_factor_numerator' => 11,
            'dpp_factor_denominator' => 12,
        ]);

        return $company;
    }

    private function karuniaCompany(): Company
    {
        return Company::create([
            'name' => 'Karunia Abadi',
            'slug' => 'karunia-abadi',
            'code' => 'KA',
            'currency_code' => 'IDR',
        ]);
    }

    private function client(Company $company): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
    }

    private function draftInvoice(Company $company, Client $client, PricingMode $pricingMode, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'pricing_mode' => $pricingMode,
            'currency_code' => 'IDR',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ], $overrides));
    }

    public function test_rp10_000_000_exclusive_example(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);

        $this->assertSame('11100000.00', $issued->total);
        $this->assertSame('1100000.00', $issued->taxSnapshot->tax_total);
    }

    public function test_rp11_100_000_inclusive_example(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Inclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 11100000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);

        $this->assertSame('11100000.00', $issued->total);
        $this->assertSame('1100000.00', $issued->taxSnapshot->tax_total);
    }

    public function test_non_tax_company_behavior(): void
    {
        $company = $this->karuniaCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 2,
            'unit_cost' => 500000,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);

        $this->assertSame('0.00', $issued->taxSnapshot->tax_total);
        $this->assertSame('1000000.00', $issued->total);
        $this->assertSame($issued->subtotal, $issued->total);
    }

    public function test_line_level_percentage_discount_applies_before_tax(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        // Gross 10,000,000 less 10% line discount = 9,000,000 taxable base.
        // Tax = 9,000,000 * 11/12 * 12% = 990,000. Total = 9,990,000.
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'discount' => 10,
            'discount_is_percentage' => true,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);

        $this->assertSame('9000000.00', $issued->subtotal);
        $this->assertSame('990000.00', $issued->taxSnapshot->tax_total);
        $this->assertSame('9990000.00', $issued->total);
    }

    public function test_document_level_nominal_discount_applies_before_tax(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive, [
            'discount' => 1000000,
            'discount_is_percentage' => false,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);

        // Taxable base = 10,000,000 - 1,000,000 = 9,000,000.
        // Tax = 9,000,000 * 11/12 * 12% = 990,000. Total = 9,990,000.
        $this->assertSame('10000000.00', $issued->subtotal);
        $this->assertSame('990000.00', $issued->taxSnapshot->tax_total);
        $this->assertSame('9990000.00', $issued->total);
        $this->assertSame('1000000.00', $issued->taxSnapshot->discount_total);
    }

    public function test_ceiling_rounding_rounds_the_final_fractional_rupiah_upward(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        // Selling price 100 * 11/12 * 12% = 11.00 exactly is too clean —
        // pick a unit cost that produces a fractional pre-round total.
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 100.03,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);
        $snapshot = $issued->taxSnapshot;

        $preRound = (float) $snapshot->pre_round_total;
        $this->assertNotSame($preRound, floor($preRound), 'Fixture should produce a fractional pre-round total.');

        $this->assertSame((string) ceil($preRound), rtrim(rtrim($issued->total, '0'), '.'));
        $this->assertSame(
            round(ceil($preRound) - $preRound, 2),
            (float) $snapshot->rounding_adjustment,
        );
    }

    public function test_reissuing_an_already_issued_invoice_is_denied(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 1000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);

        $this->expectException(RuntimeException::class);

        app(IssueInvoice::class)->issue($issued->fresh());
    }

    public function test_original_invoice_snapshot_is_retained_after_amendment(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);
        $originalNumber = $issued->number;
        $originalTotal = $issued->total;
        $originalSnapshotId = $issued->taxSnapshot->id;

        $amended = app(AmendIssuedInvoice::class)->amend($issued->fresh(), 'Corrected quantity', [
            [
                'title' => 'Service (corrected)',
                'quantity' => 1,
                'unit_cost' => 12000000,
                'tax_category' => TaxCategory::StandardTaxable,
            ],
        ]);

        $original = $issued->fresh(['taxSnapshot']);

        $this->assertSame($originalNumber, $original->number);
        $this->assertSame($originalTotal, $original->total);
        $this->assertSame($originalSnapshotId, $original->taxSnapshot->id);
        $this->assertTrue($original->status === InvoiceStatus::Amended);

        $this->assertNotSame($originalNumber, $amended->number);
        $this->assertStringContainsString('-INV-A-', $amended->number);
        $this->assertSame($original->id, $amended->original_invoice_id);
        $this->assertTrue($amended->status === InvoiceStatus::Issued);
    }

    public function test_void_and_reissue_preserves_the_original_and_issues_a_plain_new_number(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice);
        $originalNumber = $issued->number;

        $reissued = app(VoidAndReissueInvoice::class)->voidAndReissue($issued->fresh(), 'Wrong client tax details', [
            [
                'title' => 'Service (reissued)',
                'quantity' => 1,
                'unit_cost' => 10000000,
                'tax_category' => TaxCategory::StandardTaxable,
            ],
        ]);

        $original = $issued->fresh();

        $this->assertTrue($original->status === InvoiceStatus::Void);
        $this->assertSame('Wrong client tax details', $original->void_reason);
        $this->assertSame($originalNumber, $original->number);

        $this->assertStringNotContainsString('-INV-A-', $reissued->number);
        $this->assertNotSame($originalNumber, $reissued->number);
        $this->assertSame($original->id, $reissued->original_invoice_id);
        $this->assertTrue($reissued->status === InvoiceStatus::Issued);
    }
}
