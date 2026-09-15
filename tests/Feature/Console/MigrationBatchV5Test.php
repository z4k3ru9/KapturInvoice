<?php

namespace Tests\Feature\Console;

use App\Enums\MigrationBatchStatus;
use App\Enums\MigrationExceptionSeverity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\MigrationBatch;
use App\Models\MigrationException;
use App\Models\Payment;
use App\Models\ReconciliationRun;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the Phase 07 migration-batch infrastructure wrapped around
 * `import:invoiceninja-v5` (docs/rebuild/specs/07-migration-and-cutover/Specs.md):
 * batch tracking + per-step checkpointing, `--resume`, and credit exception
 * quarantine. See ImportInvoiceNinjaV5Test for the base fixture-seeding
 * pattern this reuses (a synthetic SQLite "legacy dump" standing in for a
 * restored MySQL one).
 */
class MigrationBatchV5Test extends TestCase
{
    use RefreshDatabase;

    private const CONN = 'legacy_test_migration_v5';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.'.self::CONN => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
    }

    /**
     * @param  bool  $withPayments  false omits the `payments`/`paymentables`
     *                              tables entirely, so `importPayments` fails
     *                              with a real "no such table" QueryException
     *                              — used to simulate a genuine mid-import
     *                              failure without faking an exception.
     */
    private function createLegacySchema(bool $withPayments = true): void
    {
        $conn = self::CONN;

        Schema::connection($conn)->create('companies', fn ($t) => $t->id());

        Schema::connection($conn)->create('tax_rates', function ($t) {
            $t->id();
            $t->unsignedInteger('company_id');
            $t->string('name')->nullable();
            $t->decimal('rate', 10, 3)->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('products', function ($t) {
            $t->id();
            $t->unsignedInteger('company_id');
            $t->string('product_key')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('cost', 15, 4)->nullable();
            $t->decimal('price', 15, 4)->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('clients', function ($t) {
            $t->id();
            $t->unsignedInteger('company_id');
            $t->string('name')->nullable();
            $t->string('phone')->nullable();
            $t->string('website')->nullable();
            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->string('vat_number')->nullable();
            $t->string('id_number')->nullable();
            $t->decimal('balance', 15, 2)->default(0);
            $t->decimal('paid_to_date', 15, 2)->default(0);
            $t->text('private_notes')->nullable();
            $t->text('public_notes')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection($conn)->create('client_contacts', function ($t) {
            $t->id();
            $t->unsignedInteger('client_id');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('is_primary')->default(false);
        });

        foreach (['vendors', 'vendor_contacts', 'expense_categories', 'projects', 'task_statuses', 'tasks', 'expenses', 'credits'] as $emptyTable) {
            Schema::connection($conn)->create($emptyTable, function ($t) {
                $t->id();
                $t->unsignedInteger('company_id')->nullable();
                $t->unsignedInteger('client_id')->nullable();
                $t->unsignedInteger('vendor_id')->nullable();
                $t->unsignedInteger('invoice_id')->nullable();
                $t->unsignedInteger('project_id')->nullable();
                $t->string('name')->nullable();
                $t->string('number')->nullable();
                $t->string('first_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('email')->nullable();
                $t->string('phone')->nullable();
                $t->string('website')->nullable();
                $t->string('address1')->nullable();
                $t->string('address2')->nullable();
                $t->string('city')->nullable();
                $t->string('state')->nullable();
                $t->string('postal_code')->nullable();
                $t->decimal('task_rate', 10, 2)->nullable();
                $t->decimal('budgeted_hours', 10, 2)->nullable();
                $t->date('due_date')->nullable();
                $t->text('private_notes')->nullable();
                $t->text('public_notes')->nullable();
                $t->boolean('is_primary')->default(false);
                $t->integer('status_order')->nullable();
                $t->integer('status_sort_order')->nullable();
                $t->date('date')->nullable();
                $t->decimal('exchange_rate', 10, 4)->nullable();
                $t->decimal('amount', 15, 2)->nullable();
                $t->decimal('balance', 15, 2)->nullable();
                $t->boolean('should_be_invoiced')->default(false);
                $t->string('transaction_reference')->nullable();
                $t->string('tax_name1')->nullable();
                $t->decimal('tax_rate1', 10, 3)->nullable();
                $t->string('tax_name2')->nullable();
                $t->decimal('tax_rate2', 10, 3)->nullable();
                $t->string('tax_name3')->nullable();
                $t->decimal('tax_rate3', 10, 3)->nullable();
                $t->timestamp('deleted_at')->nullable();
                $t->boolean('is_deleted')->default(false);
            });
        }

        foreach (['invoices', 'quotes'] as $table) {
            Schema::connection($conn)->create($table, function ($t) {
                $t->id();
                $t->unsignedInteger('company_id');
                $t->unsignedInteger('client_id')->nullable();
                $t->string('number')->nullable();
                $t->string('po_number')->nullable();
                $t->date('date')->nullable();
                $t->date('due_date')->nullable();
                $t->decimal('amount', 15, 2)->default(0);
                $t->decimal('discount', 15, 2)->default(0);
                $t->boolean('is_amount_discount')->default(false);
                $t->decimal('partial', 15, 2)->nullable();
                $t->date('partial_due_date')->nullable();
                $t->text('terms')->nullable();
                $t->text('public_notes')->nullable();
                $t->text('private_notes')->nullable();
                $t->text('footer')->nullable();
                $t->boolean('auto_bill_enabled')->default(false);
                $t->text('line_items')->nullable();
                $t->string('tax_name1')->nullable();
                $t->decimal('tax_rate1', 10, 3)->nullable();
                $t->boolean('uses_inclusive_taxes')->default(false);
                $t->unsignedInteger('invoice_id')->nullable();
                $t->timestamp('deleted_at')->nullable();
            });
        }

        foreach (['invoice_invitations' => 'invoice_id', 'quote_invitations' => 'quote_id'] as $table => $fk) {
            Schema::connection($conn)->create($table, function ($t) use ($fk) {
                $t->id();
                $t->unsignedInteger($fk);
                $t->unsignedInteger('client_contact_id')->nullable();
                $t->string('key')->nullable();
                $t->timestamp('sent_date')->nullable();
                $t->timestamp('viewed_date')->nullable();
                $t->timestamp('signature_date')->nullable();
                $t->text('signature_base64')->nullable();
            });
        }

        if ($withPayments) {
            $this->createPaymentsTables();
        }
    }

    private function createPaymentsTables(): void
    {
        $conn = self::CONN;

        Schema::connection($conn)->create('payments', function ($t) {
            $t->id();
            $t->unsignedInteger('company_id');
            $t->unsignedInteger('client_id')->nullable();
            $t->unsignedTinyInteger('status_id')->default(4);
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('refunded', 15, 2)->default(0);
            $t->date('date')->nullable();
            $t->string('transaction_reference')->nullable();
            $t->text('private_notes')->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('paymentables', function ($t) {
            $t->id();
            $t->unsignedInteger('payment_id');
            $t->unsignedInteger('paymentable_id');
            $t->string('paymentable_type')->nullable();
        });
    }

    private function seedClientAndInvoice(): void
    {
        $conn = self::CONN;
        $db = fn (string $table) => DB::connection($conn)->table($table);

        $db('companies')->insert(['id' => 2]);
        $db('clients')->insert(['id' => 11, 'company_id' => 2, 'name' => 'PT. Example', 'balance' => 0]);
        $db('client_contacts')->insert(['id' => 11, 'client_id' => 11, 'first_name' => 'Budi', 'email' => 'budi@example.id', 'is_primary' => true]);

        $lineItems = json_encode([
            ['product_key' => 'RB750', 'notes' => 'Mikrotik router', 'cost' => 100000, 'quantity' => 1, 'discount' => 0, 'is_amount_discount' => false],
        ]);

        $db('invoices')->insert([
            'id' => 21, 'company_id' => 2, 'client_id' => 11, 'number' => 'ATI-INV-0001',
            'date' => '2025-01-01', 'due_date' => '2025-01-15', 'line_items' => $lineItems,
            // Matches the single line item (no tax rate on the item
            // itself, and the target company defaults to non-taxable) —
            // used by the reconciliation test.
            'amount' => 100000,
        ]);
        $db('tax_rates')->insert(['id' => 1, 'company_id' => 2, 'name' => 'PPN', 'rate' => 11]);
    }

    public function test_rerunning_the_import_does_not_duplicate_rows(): void
    {
        $this->createLegacySchema();
        $this->seedClientAndInvoice();

        $conn = self::CONN;
        DB::connection($conn)->table('payments')->insert([
            'id' => 1, 'company_id' => 2, 'client_id' => 11, 'status_id' => 4, 'amount' => 100000, 'date' => '2025-01-10',
        ]);
        DB::connection($conn)->table('paymentables')->insert(['id' => 1, 'payment_id' => 1, 'paymentable_id' => 21, 'paymentable_type' => 'invoices']);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(0);

        $this->assertSame(1, Client::where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
        $this->assertSame(1, Payment::where('company_id', $company->id)->count());
        $this->assertSame(1, TaxRate::where('company_id', $company->id)->count());

        // Re-running against the exact same source data must not double up
        // any of the rows created above.
        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(0);

        $this->assertSame(1, Client::where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
        $this->assertSame(1, Payment::where('company_id', $company->id)->count());
        $this->assertSame(1, TaxRate::where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::where('company_id', $company->id)->first()->items()->count());

        $this->assertSame(2, MigrationBatch::query()->where('company_id', $company->id)->count());
        $this->assertTrue(MigrationBatch::query()->where('company_id', $company->id)->get()->every(
            fn (MigrationBatch $batch) => $batch->status === MigrationBatchStatus::Completed
        ));
    }

    public function test_a_failure_records_the_batch_and_resume_completes_without_duplicating_earlier_steps(): void
    {
        // No payments/paymentables tables -> importPayments throws a real
        // QueryException, simulating a genuine mid-import failure rather
        // than a faked one.
        $this->createLegacySchema(withPayments: false);
        $this->seedClientAndInvoice();

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);
        $conn = self::CONN;

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(1);

        $batch = MigrationBatch::query()->where('company_id', $company->id)->sole();
        $this->assertSame(MigrationBatchStatus::Failed, $batch->status);
        $this->assertSame('importCredits', $batch->last_completed_step);
        $this->assertNotNull($batch->error_message);
        $this->assertSame(1, $batch->stats['clients'] ?? null);
        $this->assertSame(1, $batch->stats['invoices'] ?? null);

        // Earlier steps really did commit despite the later failure.
        $this->assertSame(1, Client::where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());

        // Now the source dump is fixed up (payments tables restored) and
        // the same batch is resumed.
        $this->createPaymentsTables();
        DB::connection($conn)->table('payments')->insert([
            'id' => 1, 'company_id' => 2, 'client_id' => 11, 'status_id' => 4, 'amount' => 100000, 'date' => '2025-01-10',
        ]);
        DB::connection($conn)->table('paymentables')->insert(['id' => 1, 'payment_id' => 1, 'paymentable_id' => 21, 'paymentable_type' => 'invoices']);

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2, '--resume' => true])
            ->assertExitCode(0);

        $batch->refresh();
        $this->assertSame(MigrationBatchStatus::Completed, $batch->status);
        $this->assertSame('bumpNumberingSequences', $batch->last_completed_step);

        // Still exactly one batch row — resume reused it rather than
        // creating a second one.
        $this->assertSame(1, MigrationBatch::query()->where('company_id', $company->id)->count());

        // The client/invoice steps were skipped on resume (not re-run), so
        // their stats counters were never bumped a second time.
        $this->assertSame(1, $batch->stats['clients']);
        $this->assertSame(1, $batch->stats['invoices']);
        $this->assertSame(1, $batch->stats['payments']);

        // And no duplicate rows were created for the steps that were
        // (correctly) skipped.
        $this->assertSame(1, Client::where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
        $this->assertSame(1, Payment::where('company_id', $company->id)->count());

        // The resumed run still correctly linked the payment to the
        // already-imported invoice/client, proving the in-memory id maps
        // for skipped steps were rebuilt from the database rather than
        // left empty.
        $payment = Payment::where('company_id', $company->id)->sole();
        $this->assertSame(Invoice::where('company_id', $company->id)->value('id'), $payment->invoice_id);
        $this->assertSame(Client::where('company_id', $company->id)->value('id'), $payment->client_id);
    }

    public function test_without_resume_a_failed_batch_is_left_alone_and_a_fresh_batch_is_started(): void
    {
        $this->createLegacySchema(withPayments: false);
        $this->seedClientAndInvoice();

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);
        $conn = self::CONN;

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(1);

        $this->createPaymentsTables();
        DB::connection($conn)->table('payments')->insert([
            'id' => 1, 'company_id' => 2, 'client_id' => 11, 'status_id' => 4, 'amount' => 100000, 'date' => '2025-01-10',
        ]);
        DB::connection($conn)->table('paymentables')->insert(['id' => 1, 'payment_id' => 1, 'paymentable_id' => 21, 'paymentable_type' => 'invoices']);

        // No --resume: a brand-new batch is started rather than silently
        // continuing the Failed one.
        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(0);

        $this->assertSame(2, MigrationBatch::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, MigrationBatch::query()->where('company_id', $company->id)->where('status', MigrationBatchStatus::Failed)->count());
        $this->assertSame(1, MigrationBatch::query()->where('company_id', $company->id)->where('status', MigrationBatchStatus::Completed)->count());

        // Still no duplicates even without resume, thanks to the
        // updateOrCreate keying.
        $this->assertSame(1, Client::where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
    }

    public function test_a_credit_with_an_unmapped_client_is_imported_and_quarantined(): void
    {
        $this->createLegacySchema();
        $this->seedClientAndInvoice();

        $conn = self::CONN;
        DB::connection($conn)->table('credits')->insert([
            'id' => 5, 'company_id' => 2, 'client_id' => 999, 'number' => 'CR-0001', 'amount' => 50000, 'balance' => 50000, 'date' => '2025-01-05',
        ]);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(0);

        // `credits.client_id` is a required foreign key — an unmapped
        // client means the row cannot be inserted at all (no client to
        // invent), so it's quarantined instead of persisted.
        $this->assertSame(0, Credit::where('company_id', $company->id)->where('legacy_credit_id', 5)->count());

        $exception = MigrationException::query()
            ->where('company_id', $company->id)
            ->where('entity_type', 'credit')
            ->where('source_id', 5)
            ->sole();

        $this->assertSame(MigrationExceptionSeverity::High, $exception->severity);
        $this->assertNotNull($exception->reason);
    }

    public function test_a_credit_whose_balance_exceeds_its_amount_is_quarantined_as_medium_severity(): void
    {
        $this->createLegacySchema();
        $this->seedClientAndInvoice();

        $conn = self::CONN;
        DB::connection($conn)->table('credits')->insert([
            'id' => 6, 'company_id' => 2, 'client_id' => 11, 'number' => 'CR-0002', 'amount' => 10000, 'balance' => 20000, 'date' => '2025-01-06',
        ]);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(0);

        $exception = MigrationException::query()
            ->where('company_id', $company->id)
            ->where('entity_type', 'credit')
            ->where('source_id', 6)
            ->sole();

        $this->assertSame(MigrationExceptionSeverity::Medium, $exception->severity);
    }

    public function test_a_completed_import_persists_a_reconciliation_run(): void
    {
        $this->createLegacySchema();
        $this->seedClientAndInvoice();

        $conn = self::CONN;
        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v5', ['company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2])
            ->assertExitCode(0);

        $batch = MigrationBatch::where('company_id', $company->id)->firstOrFail();
        $run = ReconciliationRun::where('company_id', $company->id)->firstOrFail();

        $this->assertSame($batch->id, $run->migration_batch_id);
        $this->assertSame(1, $run->metrics['source']['clients']);
        $this->assertSame(1, $run->metrics['target']['clients']);
        $this->assertTrue($run->metrics['checks']['invoice_total']['within_tolerance']);
    }
}
