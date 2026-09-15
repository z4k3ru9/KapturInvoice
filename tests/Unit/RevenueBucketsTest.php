<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Dashboard\RevenueBuckets;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * forPeriod() used to run 2 queries per bucket (up to 60+ for a month
 * view) - now runs 2 total, grouping in PHP instead. This covers both the
 * query-count fix and, more importantly, that the actual sums/labels are
 * byte-for-byte identical to the original per-bucket behavior: exact
 * status/type filters, correct day/month boundary inclusion, and a
 * legitimately empty bucket reading 0.0 rather than being skipped.
 */
class RevenueBucketsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        app(Tenancy::class)->set($this->company);
    }

    private function invoice(string $date, float $total, string $status = 'sent'): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => $status,
            'number' => 'INV-'.uniqid(),
            'invoice_date' => $date,
        ]);

        // `total` is a derived field written by the billing engine
        // (App\Actions\Billing\IssueInvoice/TaxCalculationService), not
        // fillable - forceFill() is this app's established test-fixture
        // pattern for setting it directly (see PaymentWorkflowTest et al.).
        $invoice->forceFill(['total' => $total])->save();

        return $invoice;
    }

    private function payment(string $date, float $amount, string $status = 'verified'): Payment
    {
        return Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status' => $status,
            'payment_date' => $date,
            'amount' => $amount,
        ]);
    }

    public function test_daily_buckets_sum_invoiced_and_collected_per_day(): void
    {
        $this->invoice('2026-03-10', 100);
        $this->invoice('2026-03-10', 50);
        $this->invoice('2026-03-12', 200);
        $this->payment('2026-03-11', 75);

        $result = RevenueBuckets::forPeriod([
            'start' => Carbon::parse('2026-03-10'),
            'end' => Carbon::parse('2026-03-12'),
            'group_by' => 'day',
        ]);

        $this->assertSame(['Mar 10', 'Mar 11', 'Mar 12'], $result['labels']);
        $this->assertSame([150.0, 0.0, 200.0], $result['invoiced']);
        $this->assertSame([0.0, 75.0, 0.0], $result['collected']);
    }

    public function test_monthly_buckets_sum_invoiced_and_collected_per_month(): void
    {
        $this->invoice('2026-01-05', 100);
        $this->invoice('2026-01-28', 40);
        $this->invoice('2026-02-15', 300);
        $this->payment('2026-02-01', 60);

        $result = RevenueBuckets::forPeriod([
            'start' => Carbon::parse('2026-01-01'),
            'end' => Carbon::parse('2026-02-28'),
            'group_by' => 'month',
        ]);

        $this->assertSame(['Jan 2026', 'Feb 2026'], $result['labels']);
        $this->assertSame([140.0, 300.0], $result['invoiced']);
        $this->assertSame([0.0, 60.0], $result['collected']);
    }

    public function test_excludes_draft_invoices_and_only_counts_verified_or_completed_payments(): void
    {
        $this->invoice('2026-03-10', 100, status: 'draft');
        $this->invoice('2026-03-10', 100, status: 'sent');
        $this->payment('2026-03-10', 50, status: 'pending');
        $this->payment('2026-03-10', 25, status: 'verified');
        $this->payment('2026-03-10', 10, status: 'completed');

        $result = RevenueBuckets::forPeriod([
            'start' => Carbon::parse('2026-03-10'),
            'end' => Carbon::parse('2026-03-10'),
            'group_by' => 'day',
        ]);

        $this->assertSame([100.0], $result['invoiced']);
        $this->assertSame([35.0], $result['collected']);
    }

    public function test_query_count_stays_flat_as_the_period_grows(): void
    {
        $this->invoice('2026-03-15', 100);
        $this->payment('2026-03-15', 50);

        $queryCountFor = function (Carbon $start, Carbon $end) {
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });

            RevenueBuckets::forPeriod(['start' => $start, 'end' => $end, 'group_by' => 'day']);

            DB::flushQueryLog();

            return $count;
        };

        $small = $queryCountFor(Carbon::parse('2026-03-15'), Carbon::parse('2026-03-16'));
        $large = $queryCountFor(Carbon::parse('2026-02-01'), Carbon::parse('2026-03-31'));

        $this->assertSame($small, $large, 'A longer period must not issue more queries than a short one.');
        $this->assertSame(2, $small, 'Exactly one query for invoices and one for payments, regardless of bucket count.');
    }
}
