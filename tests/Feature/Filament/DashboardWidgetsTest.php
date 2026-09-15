<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The post-login dashboard — see docs/filament-admin-layout-design.md §9.
 * Replaces Filament's stock "framework info" dashboard with real stats, a
 * revenue trend chart + accessible table, and an expiring-quotations list.
 */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_page_renders(): void
    {
        $this->get(Dashboard::getUrl(tenant: $this->company))->assertOk();
    }

    public function test_revenue_overview_computes_this_months_revenue_outstanding_overdue_open_quotations_and_active_jobs(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'paid', 'number' => 'INV-0001']);
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id, 'invoice_id' => $invoice->id,
            'amount' => 150, 'status' => 'completed', 'payment_date' => '2026-09-10',
        ]);
        // Outside this month — must not count toward "this month" revenue.
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'amount' => 999, 'status' => 'completed', 'payment_date' => '2026-08-01',
        ]);

        $pending = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0002', 'due_date' => '2026-09-30',
        ]);
        $pending->forceFill(['balance' => 200])->save();

        $overdue = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0003', 'due_date' => '2026-09-01',
        ]);
        $overdue->forceFill(['balance' => 75])->save();

        Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => QuotationStatus::Sent, 'number' => 'QUO-0001',
        ]);

        Livewire::test(RevenueOverview::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Total revenue')
            ->assertSee('US$150')
            ->assertDontSee('US$999')
            ->assertSee('Outstanding balance')
            ->assertSee('US$275')
            ->assertSee('Overdue invoices')
            ->assertSee('US$75')
            ->assertSee('Open quotations')
            ->assertSee('1');
    }

    /**
     * Regression: RevenueOverview's "Total revenue" and RevenueBuckets'
     * chart data only checked PaymentStatus::Completed (a legacy-imported
     * status) — a payment recorded and verified through the real Phase 04
     * lifecycle (RecordCustomerPayment -> VerifyCustomerPayment) ends up
     * Verified, not Completed, and was silently excluded from both.
     */
    public function test_a_verified_not_just_completed_payment_counts_toward_total_revenue(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'paid', 'number' => 'INV-0001']);
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id, 'invoice_id' => $invoice->id,
            'amount' => 300, 'status' => 'verified', 'payment_date' => '2026-09-05',
        ]);

        Livewire::test(RevenueOverview::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Total revenue')
            ->assertSee('US$300');
    }

    /**
     * Regression: openInvoicesQuery() only recognized Sent/Viewed/Partial
     * (pre-Phase-04 legacy-import statuses) as "open" — an invoice issued
     * through the real App\Actions\Billing\IssueInvoice pipeline and never
     * paid stays at InvoiceStatus::Issued forever
     * (RecalculateInvoiceReceivables::BILLABLE_STATES), so it was
     * completely invisible to "Outstanding balance"/"Overdue invoices"
     * until a payment happened to touch it.
     */
    public function test_an_issued_and_unpaid_invoice_counts_as_outstanding_and_overdue(): void
    {
        $issued = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => InvoiceStatus::Issued, 'number' => 'INV-0004', 'due_date' => '2026-09-01',
        ]);
        $issued->forceFill(['total' => 500, 'balance' => 500])->save();

        Livewire::test(RevenueOverview::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Outstanding balance')
            ->assertSee('US$500')
            ->assertSee('Overdue invoices')
            ->assertSee('US$500 overdue');
    }

    public function test_revenue_trend_chart_and_table_share_the_same_buckets(): void
    {
        $invoiced = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0010',
            'invoice_date' => '2026-09-10',
        ]);
        $invoiced->forceFill(['total' => 500])->save();
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'amount' => 100, 'status' => 'completed', 'payment_date' => '2026-09-10',
        ]);
        // A Verified (not just Completed) payment on the same day must
        // also count toward "collected" — same regression as above.
        Payment::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'amount' => 50, 'status' => 'verified', 'payment_date' => '2026-09-10',
        ]);

        Livewire::test(RevenueTrendChart::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertOk();

        Livewire::test(RevenueTrendTable::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Sep 10')
            ->assertSee('US$500')
            ->assertSee('US$150');
    }

    public function test_expiring_quotations_widget_lists_sent_quotations_due_within_7_days_or_already_past(): void
    {
        $expiringSoon = Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => QuotationStatus::Sent, 'number' => 'QUO-0001', 'valid_until' => '2026-09-20',
        ]);
        $alreadyExpired = Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => QuotationStatus::Sent, 'number' => 'QUO-0002', 'valid_until' => '2026-09-01',
        ]);
        $farOut = Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => QuotationStatus::Sent, 'number' => 'QUO-0003', 'valid_until' => '2026-12-01',
        ]);
        $stillDraft = Quotation::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'status' => QuotationStatus::Draft, 'number' => 'QUO-0004', 'valid_until' => '2026-09-18',
        ]);

        Livewire::test(ExpiringQuotationsWidget::class)
            ->assertCanSeeTableRecords([$expiringSoon, $alreadyExpired])
            ->assertCanNotSeeTableRecords([$farOut, $stillDraft]);
    }
}
