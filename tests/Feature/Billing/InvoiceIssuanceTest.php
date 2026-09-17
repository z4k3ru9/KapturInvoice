<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\AmendIssuedInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\VoidAndReissueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Enums\UnitOfMeasure;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyTaxSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
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

    /** An Owner user attached to the company — satisfies both IssueInvoice's and AmendIssuedInvoice/VoidAndReissueInvoice's role checks. */
    private function owner(Company $company): User
    {
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        return $user;
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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));
        $snapshot = $issued->taxSnapshot;

        $preRound = (float) $snapshot->pre_round_total;
        $this->assertNotSame($preRound, floor($preRound), 'Fixture should produce a fractional pre-round total.');

        $this->assertSame((string) ceil($preRound), rtrim(rtrim($issued->total, '0'), '.'));
        $this->assertSame(
            round(ceil($preRound) - $preRound, 2),
            (float) $snapshot->rounding_adjustment,
        );
    }

    public function test_a_backdated_invoice_is_numbered_from_its_own_document_date_not_today(): void
    {
        // Codex review finding on PR #4: App\Services\DocumentNumberGenerator
        // always used now() for both the sequence year and the embedded
        // YYYYMM, so an invoice backdated into an open prior month/year got
        // a number disagreeing with its own invoice_date.
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive, [
            'invoice_date' => '2025-01-15',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 1000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

        $this->assertStringContainsString('-INV-202501', $issued->number);
        $this->assertSame('2025-01', $issued->taxRecap->reporting_period);
    }

    public function test_issuance_is_denied_for_an_unauthorized_role(): void
    {
        // Codex review finding on PR #4: IssueInvoice had no role check at
        // all — a Sales/Staff user's table-action click was the only
        // gate, so a direct call bypassed "Issue invoice: Accountant and
        // higher" (docs/rebuild/Specs.md §10) entirely.
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

        $staff = User::factory()->create();
        $company->users()->attach($staff, ['role' => 'staff']);

        $this->expectException(RuntimeException::class);

        app(IssueInvoice::class)->issue($invoice, $staff);
    }

    public function test_a_quote_or_recurring_template_cannot_be_issued_as_an_invoice(): void
    {
        // Codex review finding on PR #4: the Issue table action was
        // visible for any `invoices` row matching a Draft/Approved status
        // regardless of `type`/`is_recurring`, so a type=Quote row or a
        // recurring template could receive a real invoice number/tax
        // snapshot/recap through this path.
        $company = $this->axenCompany();
        $client = $this->client($company);
        $owner = $this->owner($company);

        $quote = $this->draftInvoice($company, $client, PricingMode::Exclusive, ['type' => 'quote']);
        InvoiceItem::create(['invoice_id' => $quote->id, 'title' => 'Service', 'quantity' => 1, 'unit_cost' => 1000000, 'tax_category' => TaxCategory::StandardTaxable]);

        $this->expectException(RuntimeException::class);
        app(IssueInvoice::class)->issue($quote, $owner);
    }

    public function test_a_recurring_template_cannot_be_issued_as_an_invoice(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $owner = $this->owner($company);

        $template = $this->draftInvoice($company, $client, PricingMode::Exclusive, ['is_recurring' => true]);
        InvoiceItem::create(['invoice_id' => $template->id, 'title' => 'Service', 'quantity' => 1, 'unit_cost' => 1000000, 'tax_category' => TaxCategory::StandardTaxable]);

        $this->expectException(RuntimeException::class);
        app(IssueInvoice::class)->issue($template, $owner);
    }

    public function test_amendment_and_void_reissue_carry_over_the_original_document_discount(): void
    {
        // Codex review finding on PR #4: AmendIssuedInvoice/
        // VoidAndReissueInvoice copied pricing_mode/currency but dropped
        // discount/discount_is_percentage, so a corrected invoice with
        // otherwise-identical items recalculated with zero global
        // discount and a higher payable total than the original.
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive, [
            'discount' => 10,
            'discount_is_percentage' => true,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

        $amended = app(AmendIssuedInvoice::class)->amend($issued->fresh(), 'Corrected quantity', [
            [
                'title' => 'Service (corrected)',
                'quantity' => 1,
                'unit_cost' => 10000000,
                'tax_category' => TaxCategory::StandardTaxable,
            ],
        ], $this->owner($company));

        $this->assertSame('10.00', (string) $amended->discount);
        $this->assertTrue((bool) $amended->discount_is_percentage);
        // Same discounted total as the original, not the undiscounted total.
        $this->assertSame($issued->fresh()->total, $amended->total);
    }

    public function test_amendment_and_void_reissue_carry_over_the_corrected_item_unit(): void
    {
        $company = $this->axenCompany();
        $client = $this->client($company);
        $invoice = $this->draftInvoice($company, $client, PricingMode::Exclusive);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Cable',
            'quantity' => 5,
            'unit' => UnitOfMeasure::Roll,
            'unit_cost' => 100000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

        $amended = app(AmendIssuedInvoice::class)->amend($issued->fresh(), 'Corrected unit', [
            [
                'title' => 'Cable (corrected)',
                'quantity' => 5,
                'unit' => UnitOfMeasure::Meter->value,
                'unit_cost' => 100000,
                'tax_category' => TaxCategory::StandardTaxable,
            ],
        ], $this->owner($company));

        $this->assertSame(UnitOfMeasure::Meter, $amended->items->first()->unit);

        $reissued = app(VoidAndReissueInvoice::class)->voidAndReissue($amended->fresh(), 'Wrong unit again', [
            [
                'title' => 'Cable (final)',
                'quantity' => 5,
                'unit' => UnitOfMeasure::Roll->value,
                'unit_cost' => 100000,
                'tax_category' => TaxCategory::StandardTaxable,
            ],
        ], $this->owner($company));

        $this->assertSame(UnitOfMeasure::Roll, $reissued->items->first()->unit);
    }

    public function test_amendment_is_denied_for_an_unauthorized_role(): void
    {
        // "Amend issued document: Admin/Owner for document edits" —
        // docs/rebuild/Specs.md §10 — Accountant is enough to *issue* but
        // not enough to *amend/void-and-reissue*.
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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

        $accountant = User::factory()->create();
        $company->users()->attach($accountant, ['role' => 'accountant']);

        $this->expectException(RuntimeException::class);

        app(AmendIssuedInvoice::class)->amend($issued->fresh(), 'Should be denied', [
            ['title' => 'x', 'quantity' => 1, 'unit_cost' => 1000000, 'tax_category' => TaxCategory::StandardTaxable],
        ], $accountant);
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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

        $this->expectException(RuntimeException::class);

        app(IssueInvoice::class)->issue($issued->fresh(), $this->owner($company));
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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));
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
        ], $this->owner($company));

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

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));
        $originalNumber = $issued->number;

        $reissued = app(VoidAndReissueInvoice::class)->voidAndReissue($issued->fresh(), 'Wrong client tax details', [
            [
                'title' => 'Service (reissued)',
                'quantity' => 1,
                'unit_cost' => 10000000,
                'tax_category' => TaxCategory::StandardTaxable,
            ],
        ], $this->owner($company));

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
