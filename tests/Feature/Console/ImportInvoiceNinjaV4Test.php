<?php

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises `import:invoiceninja-v4` end-to-end against a small synthetic
 * "legacy" database (an in-memory SQLite connection standing in for a
 * restored MySQL dump — the command only uses the query builder, not
 * MySQL-specific SQL, so this is a faithful stand-in) rather than the real
 * production dump the command was actually built and hand-verified
 * against (see docs/data-import.md — real totals/payments reconciled to
 * within 0.1%, real PII never committed here).
 *
 * Covers the core pipeline (client -> contact -> invoice -> item -> tax ->
 * payment -> recomputed status/balance), not every legacy table.
 */
class ImportInvoiceNinjaV4Test extends TestCase
{
    use RefreshDatabase;

    private const CONN = 'legacy_test_v4';

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

    private function createLegacySchema(): void
    {
        $conn = self::CONN;

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

    public function test_imports_a_client_invoice_item_and_payment_with_correctly_derived_totals(): void
    {
        $conn = self::CONN;
        $db = fn (string $table) => DB::connection($conn)->table($table);

        $db('accounts')->insert(['id' => 1]);
        $db('clients')->insert([
            'id' => 1, 'account_id' => 1, 'name' => 'Acme Surveillance', 'balance' => 999, 'paid_to_date' => 0,
        ]);
        $db('contacts')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@example.com', 'is_primary' => true,
        ]);
        $db('invoices')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'invoice_type_id' => 1,
            'invoice_number' => 'INV-0001', 'invoice_date' => '2025-01-01', 'due_date' => '2025-01-15',
        ]);
        $db('invoice_items')->insert([
            'id' => 1, 'account_id' => 1, 'invoice_id' => 1, 'product_key' => 'CAM-2MP', 'notes' => 'HIKVISION Camera',
            'cost' => 100000, 'qty' => 2, 'tax_name1' => 'PPN 11%', 'tax_rate1' => 11,
        ]);
        $db('invitations')->insert([
            'id' => 1, 'account_id' => 1, 'invoice_id' => 1, 'contact_id' => 1, 'invitation_key' => 'abc123',
            'sent_date' => '2025-01-01 10:00:00',
        ]);
        $db('payments')->insert([
            'id' => 1, 'account_id' => 1, 'client_id' => 1, 'invoice_id' => 1, 'contact_id' => 1,
            'payment_type_id' => 2, 'amount' => 222000, 'payment_status_id' => 4, 'payment_date' => '2025-01-10',
        ]);
        $db('payment_types')->insert(['id' => 2, 'name' => 'Bank Transfer']);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v4', ['company' => 'test-co', '--connection' => $conn])
            ->assertExitCode(0);

        $client = Client::where('company_id', $company->id)->first();
        $this->assertNotNull($client);
        $this->assertSame('Acme Surveillance', $client->name);
        $this->assertSame('jane@example.com', $client->email); // backfilled from the primary contact
        $this->assertSame(1, Contact::where('client_id', $client->id)->count());

        $invoice = Invoice::where('company_id', $company->id)->first();
        $this->assertNotNull($invoice);
        // 2 * 100000 = 200000 subtotal, + 11% tax = 222000 total.
        $this->assertSame('200000.00', $invoice->subtotal);
        $this->assertSame('22000.00', $invoice->tax_total);
        $this->assertSame('222000.00', $invoice->total);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(1, $invoice->items->count());
        $this->assertSame(1, $invoice->items->first()->taxes->count());
        $this->assertSame(1, $invoice->invitations()->count());

        $payment = Payment::where('company_id', $company->id)->first();
        $this->assertSame('222000.00', $payment->amount);
        $this->assertSame('Bank Transfer', $payment->method);

        // clients.balance is recomputed from the imported invoice, not
        // trusted from the source row's stale value (999).
        $this->assertSame('0.00', $client->fresh()->balance);
    }
}
