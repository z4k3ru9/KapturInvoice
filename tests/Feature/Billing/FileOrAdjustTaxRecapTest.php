<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\FileOrAdjustTaxRecap;
use App\Actions\Billing\IssueInvoice;
use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyTaxSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Codex review finding on PR #4: Phase 06B Slice 1 added filing/reference/
 * attachment and audited-adjustment fields to TaxRecap, but the only
 * Filament surface rendered them read-only, with no action anywhere that
 * wrote `manual_entry_status`, filing metadata, `adjusted_by_user_id`, or
 * `adjustment_reason` — so an accountant could not actually file or
 * correct a generated recap through the application, contrary to
 * FINALIZED-DECISIONS.md §33's "adjustments require reason and audit
 * data."
 */
class FileOrAdjustTaxRecapTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Axen Technology Indonesia', 'slug' => 'axen', 'code' => 'ATI', 'currency_code' => 'IDR',
        ]);
        CompanyTaxSetting::create([
            'company_id' => $this->company->id, 'tax_enabled' => true,
            'standard_tax_rate' => 12.00, 'dpp_factor_numerator' => 11, 'dpp_factor_denominator' => 12,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function issuedTaxableInvoice(): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'pricing_mode' => PricingMode::Exclusive,
            'currency_code' => 'IDR',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'title' => 'Service', 'quantity' => 1,
            'unit_cost' => 10000000, 'tax_category' => TaxCategory::StandardTaxable,
        ]);

        return app(IssueInvoice::class)->issue($invoice, $this->userWithRole('owner'));
    }

    public function test_filing_a_recap_the_first_time_requires_no_reason(): void
    {
        $invoice = $this->issuedTaxableInvoice();
        $accountant = $this->userWithRole('accountant');

        $recap = app(FileOrAdjustTaxRecap::class)->file(
            $invoice->taxRecap,
            ['external_reference' => 'EFAKTUR-0001', 'manual_entry_status' => 'filed', 'filing_date' => now()->toDateString()],
            $accountant,
        );

        $this->assertSame('EFAKTUR-0001', $recap->external_reference);
        $this->assertSame('filed', $recap->manual_entry_status);
        $this->assertNull($recap->adjusted_at);
    }

    public function test_adjusting_an_already_filed_recap_requires_a_reason(): void
    {
        $invoice = $this->issuedTaxableInvoice();
        $accountant = $this->userWithRole('accountant');

        app(FileOrAdjustTaxRecap::class)->file(
            $invoice->taxRecap,
            ['manual_entry_status' => 'filed', 'filing_date' => now()->toDateString()],
            $accountant,
        );

        $this->expectException(RuntimeException::class);

        app(FileOrAdjustTaxRecap::class)->file($invoice->taxRecap->fresh(), ['external_reference' => 'CORRECTED'], $accountant);
    }

    public function test_adjusting_an_already_filed_recap_with_a_reason_records_audit_data(): void
    {
        $invoice = $this->issuedTaxableInvoice();
        $accountant = $this->userWithRole('accountant');

        app(FileOrAdjustTaxRecap::class)->file(
            $invoice->taxRecap,
            ['manual_entry_status' => 'filed', 'filing_date' => now()->toDateString()],
            $accountant,
        );

        $adjusted = app(FileOrAdjustTaxRecap::class)->file(
            $invoice->taxRecap->fresh(),
            ['external_reference' => 'CORRECTED'],
            $accountant,
            'Wrong reference filed originally',
        );

        $this->assertSame('CORRECTED', $adjusted->external_reference);
        $this->assertSame($accountant->id, $adjusted->adjusted_by_user_id);
        $this->assertSame('Wrong reference filed originally', $adjusted->adjustment_reason);
        $this->assertNotNull($adjusted->adjusted_at);

        $this->assertDatabaseHas('audit_events', [
            'company_id' => $this->company->id,
            'action' => 'tax_recap.adjusted',
            'reason' => 'Wrong reference filed originally',
        ]);
    }

    public function test_filing_is_denied_for_an_unauthorized_role(): void
    {
        $invoice = $this->issuedTaxableInvoice();
        $staff = $this->userWithRole('staff');

        $this->expectException(RuntimeException::class);

        app(FileOrAdjustTaxRecap::class)->file($invoice->taxRecap, ['manual_entry_status' => 'filed'], $staff);
    }

    public function test_the_invoice_view_page_renders_with_the_tax_recap_section(): void
    {
        // Also a regression check for a real, separate bug found and
        // fixed while wiring this action: App\Filament\Support\
        // DownloadPdfAction::taxRecap() combined with a `->record()`
        // override on this Infolist section used to hang the entire page
        // in infinite recursion for any issued taxable invoice (see that
        // method's own docblock) — reproduced independent of this
        // action, via a direct HTTP request, before being fixed.
        $invoice = $this->issuedTaxableInvoice();
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertSuccessful()
            ->assertSee($invoice->taxRecap->number);
    }
}
