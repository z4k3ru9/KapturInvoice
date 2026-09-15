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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 07 (docs/rebuild/specs/07-migration-and-cutover/Specs.md) batch
 * tracking/checkpointing/quarantine coverage for `import:invoiceninja-v4`.
 * Reuses the same synthetic in-memory-sqlite "legacy dump" schema pattern
 * as ImportInvoiceNinjaV4Test — see that file's docblock for why this is a
 * faithful stand-in for a real restored MySQL dump.
 */
class MigrationBatchV4Test extends TestCase
{
    use RefreshDatabase;

    private const CONN = 'legacy_test_v4_batch';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.'.self::CONN => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        $this->createLegacySchema();
    }

    private function createLegacySchema(?string $conn = null): void
    {
        $conn ??= self::CONN;

        Schema::connection($conn)->create('accounts', fn ($t) => $t->id());

        Schema::connection($conn)->create('tax_rates', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->string('name')->nullable();
            $t->decimal('rate', 10, 3)->nullable();
            $t->boolean('is_inclusive')->default(false);
        });

        Schema::connection($conn)->create('products', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->string('product_key')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('cost', 15, 4)->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('clients', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->string('name')->nullable();
            $t->string('work_phone')->nullable();
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
            $t->timestamp('updated_at')->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('contacts', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id')->nullable();
            $t->unsignedInteger('client_id');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('is_primary')->default(false);
        });

        foreach (['vendors', 'vendor_contacts', 'expense_categories', 'projects', 'task_statuses', 'tasks', 'expenses'] as $emptyTable) {
            Schema::connection($conn)->create($emptyTable, function ($t) {
                $t->id();
                $t->unsignedInteger('account_id')->nullable();
                $t->unsignedInteger('client_id')->nullable();
                $t->unsignedInteger('vendor_id')->nullable();
                $t->unsignedInteger('invoice_id')->nullable();
                $t->unsignedInteger('project_id')->nullable();
                $t->unsignedInteger('task_status_id')->nullable();
                $t->string('name')->nullable();
                $t->string('first_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('email')->nullable();
                $t->string('phone')->nullable();
                $t->string('work_phone')->nullable();
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
                $t->boolean('is_primary')->default(false);
                $t->integer('sort_order')->nullable();
                $t->text('description')->nullable();
                $t->text('time_log')->nullable();
                $t->boolean('is_running')->default(false);
                $t->integer('task_status_sort_order')->nullable();
                $t->date('expense_date')->nullable();
                $t->decimal('exchange_rate', 10, 4)->nullable();
                $t->decimal('amount', 15, 2)->nullable();
                $t->boolean('should_be_invoiced')->default(false);
                $t->string('transaction_reference')->nullable();
                $t->string('tax_name1')->nullable();
                $t->decimal('tax_rate1', 10, 3)->nullable();
                $t->string('tax_name2')->nullable();
                $t->decimal('tax_rate2', 10, 3)->nullable();
                $t->boolean('is_deleted')->default(false);
            });
        }

        Schema::connection($conn)->create('invoices', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('client_id')->nullable();
            $t->unsignedTinyInteger('invoice_type_id')->default(1);
            $t->string('invoice_number')->nullable();
            $t->string('po_number')->nullable();
            $t->date('invoice_date')->nullable();
            $t->date('due_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('discount', 15, 2)->default(0);
            $t->boolean('is_amount_discount')->default(false);
            $t->decimal('partial', 15, 2)->nullable();
            $t->date('partial_due_date')->nullable();
            $t->text('terms')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->text('invoice_footer')->nullable();
            $t->boolean('is_recurring')->default(false);
            $t->boolean('auto_bill')->default(false);
            $t->timestamp('deleted_at')->nullable();
            $t->timestamp('updated_at')->nullable();
            $t->boolean('is_deleted')->default(false);
            $t->unsignedInteger('quote_id')->nullable();
        });

        Schema::connection($conn)->create('invoice_items', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id')->nullable();
            $t->unsignedInteger('invoice_id');
            $t->unsignedInteger('product_id')->nullable();
            $t->string('product_key')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('cost', 15, 4)->nullable();
            $t->decimal('qty', 15, 4)->nullable();
            $t->decimal('discount', 15, 2)->default(0);
            $t->string('tax_name1')->nullable();
            $t->decimal('tax_rate1', 10, 3)->nullable();
            $t->string('tax_name2')->nullable();
            $t->decimal('tax_rate2', 10, 3)->nullable();
        });

        Schema::connection($conn)->create('invitations', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id')->nullable();
            $t->unsignedInteger('invoice_id');
            $t->unsignedInteger('contact_id')->nullable();
            $t->string('invitation_key')->nullable();
            $t->timestamp('sent_date')->nullable();
            $t->timestamp('viewed_date')->nullable();
            $t->timestamp('signature_date')->nullable();
            $t->text('signature_base64')->nullable();
        });

        Schema::connection($conn)->create('credits', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('client_id')->nullable();
            $t->string('credit_number')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('balance', 15, 2)->default(0);
            $t->date('credit_date')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('private_notes')->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('payments', function ($t) {
            $t->id();
            $t->unsignedInteger('account_id');
            $t->unsignedInteger('client_id')->nullable();
            $t->unsignedInteger('invoice_id')->nullable();
            $t->unsignedInteger('contact_id')->nullable();
            $t->unsignedInteger('payment_type_id')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('refunded', 15, 2)->default(0);
            $t->unsignedTinyInteger('payment_status_id')->default(4);
            $t->string('last4')->nullable();
            $t->date('payment_date')->nullable();
            $t->text('private_notes')->nullable();
            $t->string('transaction_reference')->nullable();
            $t->boolean('is_deleted')->default(false);
        });

        Schema::connection($conn)->create('payment_types', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
    }

    private function seedBaseFixture(string $conn): void
    {
        $db = fn (string $table) => DB::connection($conn)->table($table);

        $db('accounts')->insert(['id' => 1]);
        $db('clients')->insert([
            'id' => 1, 'account_id' => 1, 'name' => 'Acme Surveillance', 'balance' => 0, 'paid_to_date' => 0,
        ]);
        $db('contacts')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@example.com', 'is_primary' => true,
        ]);
        $db('invoices')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'invoice_type_id' => 1,
            'invoice_number' => 'INV-0001', 'invoice_date' => '2025-01-01', 'due_date' => '2025-01-15',
            // 2 x 100000 + 11% tax — matches what InvoiceTotalsCalculator
            // computes from the item below, for the reconciliation test.
            'amount' => 222000,
        ]);
        $db('invoice_items')->insert([
            'id' => 1, 'account_id' => 1, 'invoice_id' => 1, 'product_key' => 'CAM-2MP', 'notes' => 'HIKVISION Camera',
            'cost' => 100000, 'qty' => 2, 'tax_name1' => 'PPN 11%', 'tax_rate1' => 11,
        ]);
        $db('payments')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'invoice_id' => 1,
            'payment_type_id' => 2, 'amount' => 222000, 'payment_status_id' => 4, 'payment_date' => '2025-01-10',
        ]);
        $db('payment_types')->insert(['id' => 2, 'name' => 'Bank Transfer']);
        $db('credits')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'credit_number' => 'CR-0001',
            'amount' => 50000, 'balance' => 50000, 'credit_date' => '2025-01-05',
        ]);
    }

    public function test_re_running_the_import_creates_no_duplicate_rows(): void
    {
        $conn = self::CONN;
        $this->seedBaseFixture($conn);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn])
            ->assertExitCode(0);

        $firstCounts = [
            'clients' => Client::withTrashed()->where('company_id', $company->id)->count(),
            'invoices' => Invoice::withTrashed()->where('company_id', $company->id)->count(),
            'credits' => Credit::withTrashed()->where('company_id', $company->id)->count(),
            'payments' => Payment::withTrashed()->where('company_id', $company->id)->count(),
        ];

        $this->assertSame(['clients' => 1, 'invoices' => 1, 'credits' => 1, 'payments' => 1], $firstCounts);

        // Plain re-run, no --resume — a fresh MigrationBatch is started
        // and every step runs again against the same source rows.
        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn])
            ->assertExitCode(0);

        $secondCounts = [
            'clients' => Client::withTrashed()->where('company_id', $company->id)->count(),
            'invoices' => Invoice::withTrashed()->where('company_id', $company->id)->count(),
            'credits' => Credit::withTrashed()->where('company_id', $company->id)->count(),
            'payments' => Payment::withTrashed()->where('company_id', $company->id)->count(),
        ];

        $this->assertSame($firstCounts, $secondCounts, 'Re-running the import must update the same rows, not duplicate them.');

        $this->assertSame(2, MigrationBatch::where('company_id', $company->id)->count());
        $this->assertTrue(
            MigrationBatch::where('company_id', $company->id)->where('status', MigrationBatchStatus::Completed)->count() === 2
        );
    }

    public function test_a_failure_partway_through_records_the_checkpoint_and_resume_completes_without_reprocessing(): void
    {
        $conn = self::CONN;
        $this->seedBaseFixture($conn);

        // A second invoice with a different legacy id but the SAME
        // invoice_number as invoice #1 — this collides with the
        // invoices table's unique(company_id, number) constraint once
        // both are imported for the same company, causing a genuine
        // exception inside importInvoices() (after importTasks() has
        // already committed).
        $db = fn (string $table) => DB::connection($conn)->table($table);
        $db('invoices')->insert([
            'id' => 2, 'account_id' => 1, 'client_id' => 1, 'invoice_type_id' => 1,
            'invoice_number' => 'INV-0001', 'invoice_date' => '2025-02-01', 'due_date' => '2025-02-15',
        ]);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn])
            ->assertExitCode(1);

        $batch = MigrationBatch::where('company_id', $company->id)->firstOrFail();
        $this->assertSame(MigrationBatchStatus::Failed, $batch->status);
        $this->assertSame('importTasks', $batch->last_completed_step);
        $this->assertNotNull($batch->error_message);

        // Only steps through importTasks committed — no client/invoice
        // rows exist yet (importClientsAndContacts did commit, though).
        $this->assertSame(1, Client::withTrashed()->where('company_id', $company->id)->count());
        $this->assertSame(0, Invoice::withTrashed()->where('company_id', $company->id)->count());

        // "Fix" the bad data (remove the colliding duplicate invoice)
        // and resume.
        $db('invoices')->where('id', 2)->delete();

        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn, '--resume' => true])
            ->assertExitCode(0);

        $batch->refresh();
        $this->assertSame(MigrationBatchStatus::Completed, $batch->status);
        $this->assertSame('bumpNumberingSequences', $batch->last_completed_step);

        // Still exactly one MigrationBatch row — resumed, not duplicated.
        $this->assertSame(1, MigrationBatch::where('company_id', $company->id)->count());

        $this->assertSame(1, Client::withTrashed()->where('company_id', $company->id)->count());
        $this->assertSame(1, Invoice::withTrashed()->where('company_id', $company->id)->count());
        $this->assertSame(1, Payment::withTrashed()->where('company_id', $company->id)->count());
    }

    public function test_a_credit_with_an_unmapped_client_is_still_imported_but_quarantined(): void
    {
        $conn = self::CONN;
        $db = fn (string $table) => DB::connection($conn)->table($table);

        $db('accounts')->insert(['id' => 1]);
        $db('clients')->insert([
            'id' => 1, 'account_id' => 1, 'name' => 'Acme Surveillance', 'balance' => 0, 'paid_to_date' => 0,
        ]);

        // client_id 999 was never imported (no such client row exists).
        $db('credits')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 999, 'credit_number' => 'CR-ORPHAN',
            'amount' => 100000, 'balance' => 25000, 'credit_date' => '2025-01-05',
        ]);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn])
            ->assertExitCode(0);

        $credit = Credit::withTrashed()->where('company_id', $company->id)->where('legacy_credit_id', 1)->first();
        $this->assertNotNull($credit, 'The credit must still be imported, never skipped.');
        $this->assertNull($credit->client_id);

        $exception = MigrationException::where('company_id', $company->id)
            ->where('entity_type', 'credit')
            ->where('source_id', 1)
            ->first();

        $this->assertNotNull($exception);
        $this->assertSame(MigrationExceptionSeverity::High, $exception->severity);
        $this->assertStringContainsString('999', $exception->reason);
    }

    public function test_a_completed_import_persists_a_reconciliation_run(): void
    {
        $conn = self::CONN;
        $this->seedBaseFixture($conn);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn])
            ->assertExitCode(0);

        $batch = MigrationBatch::where('company_id', $company->id)->firstOrFail();
        $run = ReconciliationRun::where('company_id', $company->id)->firstOrFail();

        $this->assertSame($batch->id, $run->migration_batch_id);
        $this->assertSame(1, $run->metrics['source']['clients']);
        $this->assertSame(1, $run->metrics['target']['clients']);
        $this->assertTrue($run->metrics['checks']['invoice_total']['within_tolerance']);
    }

    /**
     * Phase 07's "Cross-company source IDs cannot merge records" required
     * test: two DIFFERENT source dumps that both happen to use source id
     * 1 for their client/invoice (entirely plausible — every InvoiceNinja
     * account restarts its own auto-increment ids) must import into two
     * separate target Companies without merging or leaking into each
     * other, since the idempotency key is (company_id, legacy_*_id), not
     * legacy_*_id alone.
     */
    public function test_two_companies_importing_source_rows_with_the_same_id_never_merge(): void
    {
        $connA = self::CONN;
        $this->seedBaseFixture($connA);

        $connB = self::CONN.'_other';
        config(['database.connections.'.$connB => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
        $this->createLegacySchema($connB);
        $dbB = fn (string $table) => DB::connection($connB)->table($table);
        // Same source ids (account 1, client 1, invoice 1) as $connA's
        // fixture, but a genuinely different client/invoice.
        $dbB('accounts')->insert(['id' => 1]);
        $dbB('clients')->insert(['id' => 1, 'account_id' => 1, 'name' => 'Different Company Ltd', 'balance' => 0, 'paid_to_date' => 0]);
        $dbB('invoices')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'invoice_type_id' => 1,
            'invoice_number' => 'OTHER-0001', 'invoice_date' => '2025-03-01', 'due_date' => '2025-03-15', 'amount' => 500000,
        ]);

        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'currency_code' => 'IDR']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v4', ['company' => 'company-a', '--connection' => $connA])->assertExitCode(0);
        $this->artisan('import:invoiceninja-v4', ['company' => 'company-b', '--connection' => $connB])->assertExitCode(0);

        // Both companies have their own client/invoice with legacy id 1 —
        // never merged into a single row, never cross-linked.
        $clientA = Client::where('company_id', $companyA->id)->where('legacy_client_id', 1)->sole();
        $clientB = Client::where('company_id', $companyB->id)->where('legacy_client_id', 1)->sole();

        $this->assertNotSame($clientA->id, $clientB->id);
        $this->assertSame('Acme Surveillance', $clientA->name);
        $this->assertSame('Different Company Ltd', $clientB->name);

        $invoiceA = Invoice::where('company_id', $companyA->id)->where('legacy_invoice_id', 1)->sole();
        $invoiceB = Invoice::where('company_id', $companyB->id)->where('legacy_invoice_id', 1)->sole();

        $this->assertNotSame($invoiceA->id, $invoiceB->id);
        $this->assertSame($clientA->id, $invoiceA->client_id);
        $this->assertSame($clientB->id, $invoiceB->client_id);

        // Total rows: exactly 2 clients, 2 invoices — no accidental
        // dedup/merge across companies.
        $this->assertSame(2, Client::whereIn('company_id', [$companyA->id, $companyB->id])->count());
        $this->assertSame(2, Invoice::whereIn('company_id', [$companyA->id, $companyB->id])->count());
    }
}
