<?php

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises `import:invoiceninja-v5` end-to-end against a small synthetic
 * "legacy" database — see ImportInvoiceNinjaV4Test's docblock for why
 * SQLite stands in for a restored MySQL dump here. Specifically covers
 * the two things that only exist in v5's shape: line items stored as a
 * `line_items` JSON blob (rather than a shared item table) with tax
 * applied at the invoice header instead of per item, and payments linked
 * to an invoice via `paymentables` rather than a direct `invoice_id`
 * column.
 */
class ImportInvoiceNinjaV5Test extends TestCase
{
    use RefreshDatabase;

    private const CONN = 'legacy_test_v5';

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
                $t->unsignedInteger('invoice_id')->nullable(); // quotes only: converted-to invoice
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

    public function test_imports_an_invoice_with_json_line_items_and_invoice_level_inclusive_tax(): void
    {
        $conn = self::CONN;
        $db = fn (string $table) => DB::connection($conn)->table($table);

        $db('companies')->insert(['id' => 2]);
        $db('clients')->insert(['id' => 11, 'company_id' => 2, 'name' => 'PT. Example', 'balance' => 999]);
        $db('client_contacts')->insert(['id' => 11, 'client_id' => 11, 'first_name' => 'Budi', 'email' => 'budi@example.id', 'is_primary' => true]);

        // Tax applied at the invoice header (uses_inclusive_taxes=1), the
        // real dump's dominant pattern — cost is already tax-inclusive.
        $lineItems = json_encode([
            ['product_key' => 'RB750', 'notes' => 'Mikrotik router', 'cost' => 111000, 'quantity' => 1, 'discount' => 0, 'is_amount_discount' => false],
        ]);

        $db('invoices')->insert([
            'id' => 21, 'company_id' => 2, 'client_id' => 11, 'number' => 'ATI-INV-0001',
            'date' => '2025-01-01', 'due_date' => '2025-01-15',
            'line_items' => $lineItems, 'tax_name1' => 'PPN 11%', 'tax_rate1' => 11, 'uses_inclusive_taxes' => true,
        ]);
        $db('invoice_invitations')->insert([
            'id' => 1, 'invoice_id' => 21, 'client_contact_id' => 11, 'key' => 'xyz', 'sent_date' => '2025-01-01 08:00:00',
        ]);
        $db('payments')->insert([
            'id' => 1, 'company_id' => 2, 'client_id' => 11, 'status_id' => 4, 'amount' => 111000, 'date' => '2025-01-10',
        ]);
        $db('paymentables')->insert(['id' => 1, 'payment_id' => 1, 'paymentable_id' => 21, 'paymentable_type' => 'invoices']);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v5', [
            'company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2,
        ])->assertExitCode(0);

        $invoice = Invoice::where('company_id', $company->id)->first();
        $this->assertNotNull($invoice);
        // 111000 is tax-inclusive at 11% -> exclusive subtotal 100000, tax 11000.
        $this->assertSame('100000.00', $invoice->subtotal);
        $this->assertSame('11000.00', $invoice->tax_total);
        $this->assertSame('111000.00', $invoice->total);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);

        $payment = Payment::where('company_id', $company->id)->first();
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame('111000.00', $payment->amount);

        $this->assertSame('0.00', Client::where('company_id', $company->id)->first()->balance);
    }

    public function test_uses_first_notes_line_when_product_key_is_empty_and_once_blocks_completed_reruns(): void
    {
        $conn = self::CONN;
        $db = fn (string $table) => DB::connection($conn)->table($table);

        $db('companies')->insert(['id' => 2]);
        $db('clients')->insert(['id' => 12, 'company_id' => 2, 'name' => 'Example Client']);
        $db('invoices')->insert([
            'id' => 22,
            'company_id' => 2,
            'client_id' => 12,
            'number' => 'ATI-INV-0002',
            'date' => '2025-01-02',
            'line_items' => json_encode([
                [
                    'product_key' => '',
                    'notes' => "Option 1\nIntel i5 9400F\nGigabyte B365M",
                    'cost' => 9850000,
                    'quantity' => 1,
                    'discount' => 0,
                    'is_amount_discount' => false,
                ],
            ]),
        ]);

        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co', 'currency_code' => 'IDR']);

        $this->artisan('import:invoiceninja-v5', [
            'company' => 'test-co', '--connection' => $conn, '--legacy-company-id' => 2,
        ])->assertExitCode(0);

        $item = Invoice::where('company_id', $company->id)->firstOrFail()->items()->firstOrFail();
        $this->assertSame('Option 1', $item->title);
        $this->assertSame("Option 1\nIntel i5 9400F\nGigabyte B365M", $item->description);

        $this->artisan('import:invoiceninja-v5', [
            'company' => 'test-co', '--connection' => $conn, '--once' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, Invoice::where('company_id', $company->id)->count());
    }
}
