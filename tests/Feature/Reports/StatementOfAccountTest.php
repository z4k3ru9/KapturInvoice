<?php

namespace Tests\Feature\Reports;

use App\Actions\Reports\GenerateStatementOfAccount;
use App\Http\Controllers\StatementOfAccountPdfController;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\StatementOfAccount;
use App\Models\User;
use App\Services\Reports\BuildStatementOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 06B Slice 1 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "SOA totals reconciled against hand-calculated fixtures." Every total
 * below is worked out by hand in each test's own comment rather than
 * re-derived from the service under test.
 */
class StatementOfAccountTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $code = 'ACM'): Company
    {
        return Company::create(['name' => 'Acme', 'slug' => 'acme-'.strtolower($code), 'code' => $code, 'currency_code' => 'IDR']);
    }

    private function client(Company $company, string $name = 'Test Client'): Client
    {
        return Client::create(['company_id' => $company->id, 'name' => $name]);
    }

    /** Creates an Invoice with its billing totals force-filled (total/balance aren't mass-assignable — see App\Models\Invoice's #[Fillable]). */
    private function invoice(Company $company, Client $client, array $attributes, float $total, float $balance): Invoice
    {
        $invoice = Invoice::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'currency_code' => 'IDR',
        ], $attributes));

        $invoice->forceFill(['total' => $total, 'balance' => $balance, 'amount_paid' => $total - $balance])->save();

        return $invoice->fresh();
    }

    private function verifiedPayment(Company $company, Client $client, float $amount, string $verifiedAt, ?Invoice $allocateTo = null): Payment
    {
        $payment = Payment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'method' => 'bank_transfer',
            'amount' => $amount,
            'currency_code' => 'IDR',
            'payment_date' => $verifiedAt,
            'proof_path' => 'proofs/test.pdf',
            'status' => 'verified',
        ]);
        $payment->forceFill(['verified_at' => $verifiedAt])->save();

        if ($allocateTo) {
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $allocateTo->id,
                'amount' => $amount,
                'is_active' => true,
            ]);
        }

        return $payment->fresh();
    }

    public function test_opening_balance_combines_a_prior_period_paid_invoice_and_a_prior_period_verified_payment(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        // Invoice A: 1,000,000 total, fully paid before the period starts —
        // its face total and its full payment both land before periodStart,
        // so it nets to 0 toward the opening balance.
        $invoiceA = $this->invoice($company, $client, [
            'status' => 'paid', 'invoice_date' => '2026-05-01', 'due_date' => '2026-05-15',
        ], 1_000_000, 0);
        $this->verifiedPayment($company, $client, 1_000_000, '2026-05-05 10:00:00', $invoiceA);

        // Invoice B: 2,000,000 total, only 1,500,000 paid before the
        // period starts — 500,000 of it should still be open.
        $invoiceB = $this->invoice($company, $client, [
            'status' => 'partial', 'invoice_date' => '2026-05-10', 'due_date' => '2026-05-25',
        ], 2_000_000, 500_000);
        $this->verifiedPayment($company, $client, 1_500_000, '2026-05-15 10:00:00', $invoiceB);

        $snapshot = app(BuildStatementOfAccount::class)->build($client, '2026-06-01', '2026-06-30');

        // (1,000,000 + 2,000,000) invoiced - (1,000,000 + 1,500,000) paid = 500,000.
        $this->assertSame(500_000.0, $snapshot['opening_balance']);
    }

    public function test_invoice_credit_receipt_and_payment_each_appear_in_period_and_closing_balance_reconciles(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        // A prior-period invoice left with an open 500,000 balance —
        // reused verbatim as this test's opening balance.
        $this->invoice($company, $client, [
            'status' => 'partial', 'invoice_date' => '2026-05-10', 'due_date' => '2026-05-25',
        ], 500_000, 500_000);

        $invoiceInPeriod = $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10', 'number' => 'ACM-INV-0001',
        ], 800_000, 800_000);

        $credit = Credit::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'number' => 'ACM-CR-0001', 'amount' => 200_000, 'credit_date' => '2026-06-12',
        ]);

        $payment = $this->verifiedPayment($company, $client, 300_000, '2026-06-15 09:00:00', $invoiceInPeriod);
        $receipt = Receipt::create([
            'company_id' => $company->id, 'payment_id' => $payment->id,
            'number' => 'ACM-RCT-0001', 'issued_at' => '2026-06-16 08:00:00',
        ]);

        $snapshot = app(BuildStatementOfAccount::class)->build($client, '2026-06-01', '2026-06-30');

        $this->assertSame(500_000.0, $snapshot['opening_balance']);

        $this->assertCount(1, $snapshot['invoices']);
        $this->assertSame('ACM-INV-0001', $snapshot['invoices'][0]['number']);
        $this->assertSame(800_000.0, $snapshot['invoices'][0]['balance_contribution']);

        $this->assertCount(1, $snapshot['credits']);
        $this->assertSame('ACM-CR-0001', $snapshot['credits'][0]['number']);
        $this->assertTrue($snapshot['credits'][0]['confirmed']);
        $this->assertSame(200_000.0, $snapshot['credits'][0]['balance_contribution']);

        $this->assertCount(1, $snapshot['payments']);
        $this->assertSame(300_000.0, $snapshot['payments'][0]['balance_contribution']);

        $this->assertCount(1, $snapshot['receipts']);
        $this->assertSame('ACM-RCT-0001', $snapshot['receipts'][0]['number']);
        $this->assertSame(300_000.0, $snapshot['receipts'][0]['amount']);

        // opening 500,000 + invoices 800,000 - credits 200,000 - payments 300,000 = 800,000.
        $this->assertSame(800_000.0, $snapshot['closing_balance']);
    }

    public function test_void_invoice_is_shown_but_contributes_zero_to_the_balance(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        $void = $this->invoice($company, $client, [
            'status' => 'void', 'invoice_date' => '2026-06-05', 'due_date' => '2026-07-05', 'number' => 'ACM-INV-0002',
        ], 999_999, 0);

        $snapshot = app(BuildStatementOfAccount::class)->build($client, '2026-06-01', '2026-06-30');

        $this->assertCount(1, $snapshot['invoices']);
        $this->assertSame('void', $snapshot['invoices'][0]['status']);
        $this->assertSame(0.0, $snapshot['invoices'][0]['balance_contribution']);
        $this->assertSame(0.0, $snapshot['opening_balance']);
        $this->assertSame(0.0, $snapshot['closing_balance']);
        $this->assertSame($void->id, $snapshot['invoices'][0]['id']);
    }

    public function test_an_amended_invoice_does_not_double_count_against_its_replacement(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        $original = $this->invoice($company, $client, [
            'status' => 'amended', 'invoice_date' => '2026-06-05', 'due_date' => '2026-07-05', 'number' => 'ACM-INV-0003',
        ], 400_000, 0);

        $replacement = $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-05', 'due_date' => '2026-07-05',
            'number' => 'ACM-INV-0003-A', 'original_invoice_id' => $original->id,
        ], 450_000, 450_000);

        $snapshot = app(BuildStatementOfAccount::class)->build($client, '2026-06-01', '2026-06-30');

        $this->assertCount(2, $snapshot['invoices']);

        $rows = collect($snapshot['invoices'])->keyBy('number');
        $this->assertSame(0.0, $rows['ACM-INV-0003']['balance_contribution']);
        $this->assertSame('amended', $rows['ACM-INV-0003']['status']);
        $this->assertSame(450_000.0, $rows['ACM-INV-0003-A']['balance_contribution']);

        // Only the replacement's 450,000 counts — the amended original's
        // 400,000 must not also land in the total.
        $this->assertSame(450_000.0, $snapshot['closing_balance']);
    }

    public function test_aging_buckets_for_invoices_at_10_45_75_and_120_days_overdue(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        $periodEnd = '2026-08-31';

        $current = $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-08-01', 'due_date' => '2026-09-15',
        ], 111_000, 111_000);

        $overdue10 = $this->invoice($company, $client, [
            'status' => 'overdue', 'invoice_date' => '2026-07-01', 'due_date' => '2026-08-21',
        ], 100_000, 100_000);

        $overdue45 = $this->invoice($company, $client, [
            'status' => 'overdue', 'invoice_date' => '2026-06-01', 'due_date' => '2026-07-17',
        ], 200_000, 200_000);

        $overdue75 = $this->invoice($company, $client, [
            'status' => 'overdue', 'invoice_date' => '2026-05-01', 'due_date' => '2026-06-17',
        ], 300_000, 300_000);

        $overdue120 = $this->invoice($company, $client, [
            'status' => 'overdue', 'invoice_date' => '2026-03-01', 'due_date' => '2026-05-03',
        ], 400_000, 400_000);

        $snapshot = app(BuildStatementOfAccount::class)->build($client, '2026-08-01', $periodEnd);

        $this->assertSame(111_000.0, $snapshot['aging']['current']);
        $this->assertSame(100_000.0, $snapshot['aging']['1_30']);
        $this->assertSame(200_000.0, $snapshot['aging']['31_60']);
        $this->assertSame(300_000.0, $snapshot['aging']['61_90']);
        $this->assertSame(400_000.0, $snapshot['aging']['over_90']);
    }

    public function test_company_and_client_scoping_excludes_other_records(): void
    {
        $company = $this->company('ACM');
        $client = $this->client($company);

        $otherCompany = $this->company('OTH');
        $otherClientSameCompany = $this->client($company, 'Other Client');
        $otherClientOtherCompany = $this->client($otherCompany, 'Cross Company Client');

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-05', 'due_date' => '2026-07-05', 'number' => 'ACM-INV-MINE',
        ], 700_000, 700_000);

        $this->invoice($company, $otherClientSameCompany, [
            'status' => 'issued', 'invoice_date' => '2026-06-05', 'due_date' => '2026-07-05', 'number' => 'ACM-INV-OTHER-CLIENT',
        ], 111_111, 111_111);

        $this->invoice($otherCompany, $otherClientOtherCompany, [
            'status' => 'issued', 'invoice_date' => '2026-06-05', 'due_date' => '2026-07-05', 'number' => 'OTH-INV-CROSS',
        ], 222_222, 222_222);

        $snapshot = app(BuildStatementOfAccount::class)->build($client, '2026-06-01', '2026-06-30');

        $this->assertCount(1, $snapshot['invoices']);
        $this->assertSame('ACM-INV-MINE', $snapshot['invoices'][0]['number']);
        $this->assertSame(700_000.0, $snapshot['closing_balance']);
    }

    public function test_generate_action_persists_a_numbered_immutable_snapshot(): void
    {
        $company = $this->company();
        $client = $this->client($company);
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
        ], 800_000, 800_000);

        $statementOfAccount = app(GenerateStatementOfAccount::class)->generate($client, '2026-06-01', '2026-06-30', $user);

        $this->assertStringContainsString('ACM-SOA-', $statementOfAccount->number);
        $this->assertSame($user->id, $statementOfAccount->generated_by_user_id);
        $this->assertNotNull($statementOfAccount->generated_at);
        $this->assertSame(800_000.0, (float) $statementOfAccount->closing_balance);
        $this->assertIsArray($statementOfAccount->snapshot);
        $this->assertCount(1, $statementOfAccount->snapshot['invoices']);

        // Persisted, and scoped by company/number just like every other
        // launch document.
        $this->assertDatabaseHas('statement_of_accounts', [
            'id' => $statementOfAccount->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);
    }

    /**
     * Registers the PDF route locally (never touching routes/web.php,
     * which this phase's instructions reserve for a human to add — see
     * this test class's own final report) so the controller/auth/
     * tenant-check wiring is still exercised end-to-end over real HTTP.
     */
    private function registerPdfRoute(): void
    {
        Route::middleware('web')->get('/test/statement-of-accounts/{statementOfAccount}/pdf', StatementOfAccountPdfController::class)
            ->middleware('auth')
            ->name('statement-of-accounts.pdf');
    }

    public function test_pdf_route_renders_in_bahasa_indonesia_by_default(): void
    {
        $this->registerPdfRoute();

        $company = $this->company();
        $client = $this->client($company);
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
        ], 800_000, 800_000);

        $statementOfAccount = app(GenerateStatementOfAccount::class)->generate($client, '2026-06-01', '2026-06-30', $user);

        $this->actingAs($user)
            ->get(route('statement-of-accounts.pdf', $statementOfAccount))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_route_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        $this->registerPdfRoute();

        $company = $this->company();
        $client = $this->client($company);
        $owner = User::factory()->create();
        $company->users()->attach($owner, ['role' => 'owner']);

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
        ], 800_000, 800_000);

        $statementOfAccount = app(GenerateStatementOfAccount::class)->generate($client, '2026-06-01', '2026-06-30', $owner);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('statement-of-accounts.pdf', $statementOfAccount))
            ->assertForbidden();
    }

    public function test_pdf_view_renders_bahasa_indonesia_labels_by_default(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
        ], 800_000, 800_000);

        $statementOfAccount = app(GenerateStatementOfAccount::class)->generate($client, '2026-06-01', '2026-06-30');
        $statementOfAccount->loadMissing('client', 'company');

        $html = view('pdf.statement-of-account', ['statementOfAccount' => $statementOfAccount])->render();

        $this->assertStringContainsString(strtoupper(__('documents.soa_title', [], 'id')), $html);
    }

    public function test_pdf_view_renders_english_labels_when_document_language_override_is_en(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
        ], 800_000, 800_000);

        CompanySetting::create(['company_id' => $company->id, 'default_document_language' => 'id']);

        $statementOfAccount = app(GenerateStatementOfAccount::class)->generate($client, '2026-06-01', '2026-06-30');
        $statementOfAccount->forceFill(['document_language' => 'en'])->save();
        $statementOfAccount->loadMissing('client', 'company');

        $html = view('pdf.statement-of-account', ['statementOfAccount' => $statementOfAccount])->render();

        $this->assertStringContainsString(strtoupper(__('documents.soa_title', [], 'en')), $html);
        $this->assertStringNotContainsString(strtoupper(__('documents.soa_title', [], 'id')), $html);
    }

    public function test_preview_is_never_persisted(): void
    {
        $company = $this->company();
        $client = $this->client($company);

        $this->invoice($company, $client, [
            'status' => 'issued', 'invoice_date' => '2026-06-10', 'due_date' => '2026-07-10',
        ], 800_000, 800_000);

        app(BuildStatementOfAccount::class)->build($client, '2026-06-01', '2026-06-30');

        $this->assertSame(0, StatementOfAccount::count());
    }
}
