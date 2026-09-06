<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ImportsLegacyInvoiceNinja;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\ExpenseTotalsCalculator;
use App\Services\InvoiceTotalsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports a legacy InvoiceNinja **v4** MySQL/MariaDB dump (the classic
 * unified `invoices` table, `account_id`-scoped) into KapturInvoice's own
 * schema for one target Company. See docs/data-import.md for the full
 * table-by-table mapping and known simplifications.
 *
 * Reads from the `legacy_v4` connection (config/database.php) — point it
 * at a database you've already restored the dump into, e.g.:
 *   mysql -uroot legacy_v4 < chronopr_ninj226.sql
 */
class ImportInvoiceNinjaV4 extends Command
{
    use ImportsLegacyInvoiceNinja;

    protected $signature = 'import:invoiceninja-v4
        {company : Target Company slug}
        {--legacy-account-id=1 : accounts.id in the source dump to import (a dump normally has exactly one)}
        {--connection=legacy_v4 : Laravel DB connection name for the source dump}';

    protected $description = 'Import a legacy InvoiceNinja v4 dump into one KapturInvoice Company';

    private string $conn;

    private int $accountId;

    /** @var array<int, int> legacy products.id => new Product id */
    private array $productMap = [];

    /** @var array<int, int> legacy clients.id => new Client id */
    private array $clientMap = [];

    /** @var array<int, int> legacy contacts.id => new Contact id */
    private array $contactMap = [];

    /** @var array<int, int> legacy invoices.id => new Invoice id */
    private array $invoiceMap = [];

    /** @var array<int, int> legacy vendors.id => new Vendor id */
    private array $vendorMap = [];

    /** @var array<int, int> legacy task_statuses.id => new TaskStatus id */
    private array $taskStatusMap = [];

    /** @var array<int, int> legacy projects.id => new Project id */
    private array $projectMap = [];

    public function handle(): int
    {
        $company = Company::query()->where('slug', $this->argument('company'))->first();

        if (! $company) {
            $this->error("No company with slug [{$this->argument('company')}].");

            return self::FAILURE;
        }

        $this->conn = $this->option('connection');
        $this->accountId = (int) $this->option('legacy-account-id');

        if (! DB::connection($this->conn)->getSchemaBuilder()->hasTable('accounts')) {
            $this->error("Connection [{$this->conn}] doesn't look like a restored InvoiceNinja v4 dump (no `accounts` table).");

            return self::FAILURE;
        }

        DB::transaction(function () use ($company) {
            $this->importTaxRates($company);
            $this->importProducts($company);
            $this->importClientsAndContacts($company);
            $this->importVendorsAndContacts($company);
            $this->importExpenseCategories($company);
            $this->importProjects($company);
            $this->importTaskStatuses($company);
            $this->importTasks($company);
            $this->importInvoices($company);
            $this->importExpenses($company);
            $this->importCredits($company);
            $this->importPayments($company);
            $this->finalizeInvoiceTotals();
            $this->recomputeClientBalances($company);
            $this->bumpNumberingSequences($company);
        });

        $this->printStats();
        $this->reconcile($company);

        return self::SUCCESS;
    }

    /**
     * `clients.balance`/`paid_to_date` in the source dump are a
     * hand-maintained running ledger (see
     * docs/invoiceninja-v4-schema-reference.md §2.2) — on this real dump
     * they sum to exactly 0 across all clients despite ~5B IDR of
     * genuinely outstanding invoice balances, i.e. they'd drifted stale.
     * Recomputed from the invoices/payments actually imported instead of
     * trusted as-is, per the schema doc's own §5 recommendation.
     */
    private function recomputeClientBalances(Company $company): void
    {
        foreach (Client::withTrashed()->where('company_id', $company->id)->get() as $client) {
            $balance = Invoice::withTrashed()
                ->where('client_id', $client->id)
                ->where('type', InvoiceType::Invoice)
                ->sum('balance');

            $paidToDate = Payment::query()
                ->where('client_id', $client->id)
                ->where('status', PaymentStatus::Completed)
                ->sum(DB::raw('amount - refunded_amount'));

            $client->forceFill([
                'balance' => $this->money($balance),
                'paid_to_date' => $this->money($paidToDate),
            ])->saveQuietly();
        }
    }

    private function source(string $table)
    {
        return DB::connection($this->conn)->table($table)->where('account_id', $this->accountId);
    }

    private function importTaxRates(Company $company): void
    {
        foreach ($this->source('tax_rates')->get() as $row) {
            TaxRate::create([
                'company_id' => $company->id,
                'legacy_tax_rate_id' => $row->id,
                'name' => $row->name,
                'rate' => $row->rate,
                'is_inclusive' => (bool) $row->is_inclusive,
            ]);
            $this->bump('tax_rates');
        }
    }

    private function importProducts(Company $company): void
    {
        foreach ($this->source('products')->where('is_deleted', 0)->get() as $row) {
            $product = Product::create([
                'company_id' => $company->id,
                'legacy_product_id' => $row->id,
                'sku' => $row->product_key ?: null,
                'name' => $row->notes ?: ($row->product_key ?: 'Product'),
                'description' => $row->notes,
                'unit_cost' => $row->cost ?? 0,
            ]);
            $this->productMap[$row->id] = $product->id;
            $this->bump('products');
        }
    }

    private function importClientsAndContacts(Company $company): void
    {
        foreach ($this->source('clients')->get() as $row) {
            $client = Client::create([
                'company_id' => $company->id,
                'legacy_client_id' => $row->id,
                'name' => $row->name ?: 'Client #'.$row->id,
                'currency_code' => $company->currency_code,
                'phone' => $row->work_phone,
                'website' => $row->website,
                'address_line_1' => $row->address1,
                'address_line_2' => $row->address2,
                'city' => $row->city,
                'state' => $row->state,
                'postal_code' => $row->postal_code,
                'tax_number' => $row->vat_number ?: null,
                'id_number' => $row->id_number ?: null,
                'balance' => $this->money($row->balance),
                'paid_to_date' => $this->money($row->paid_to_date),
                'notes' => trim(collect([$row->private_notes, $row->public_notes])->filter()->implode("\n\n")) ?: null,
                'deleted_at' => $row->deleted_at ?? ($row->is_deleted ? $row->updated_at : null),
            ]);
            $this->clientMap[$row->id] = $client->id;
            $this->bump('clients');

            $primaryEmail = null;

            foreach ($this->source('contacts')->where('client_id', $row->id)->get() as $contactRow) {
                $contact = Contact::create([
                    'client_id' => $client->id,
                    'legacy_contact_id' => $contactRow->id,
                    'first_name' => $contactRow->first_name ?: 'Contact',
                    'last_name' => $contactRow->last_name,
                    'email' => $contactRow->email ?: null,
                    'phone' => $contactRow->phone,
                    'is_primary' => (bool) $contactRow->is_primary,
                ]);
                $this->contactMap[$contactRow->id] = $contact->id;
                $this->bump('contacts');

                if ($contactRow->is_primary && filled($contactRow->email)) {
                    $primaryEmail = $contactRow->email;
                }
            }

            if ($primaryEmail) {
                $client->update(['email' => $primaryEmail]);
            }
        }
    }

    private function importVendorsAndContacts(Company $company): void
    {
        foreach ($this->source('vendors')->where('is_deleted', 0)->get() as $row) {
            $vendor = Vendor::create([
                'company_id' => $company->id,
                'legacy_vendor_id' => $row->id,
                'name' => $row->name ?: 'Vendor #'.$row->id,
                'phone' => $row->work_phone,
                'website' => $row->website,
                'address_line_1' => $row->address1,
                'address_line_2' => $row->address2,
                'city' => $row->city,
                'state' => $row->state,
                'postal_code' => $row->postal_code,
                'notes' => $row->private_notes,
            ]);
            $this->vendorMap[$row->id] = $vendor->id;
            $this->bump('vendors');

            foreach ($this->source('vendor_contacts')->where('vendor_id', $row->id)->get() as $contactRow) {
                VendorContact::create([
                    'vendor_id' => $vendor->id,
                    'legacy_vendor_contact_id' => $contactRow->id,
                    'first_name' => $contactRow->first_name ?: 'Contact',
                    'last_name' => $contactRow->last_name,
                    'email' => $contactRow->email ?: null,
                    'phone' => $contactRow->phone,
                    'is_primary' => (bool) $contactRow->is_primary,
                ]);
                $this->bump('vendor_contacts');
            }
        }
    }

    private function importExpenseCategories(Company $company): void
    {
        foreach ($this->source('expense_categories')->where('is_deleted', 0)->get() as $row) {
            ExpenseCategory::create([
                'company_id' => $company->id,
                'legacy_expense_category_id' => $row->id,
                'name' => $row->name,
            ]);
            $this->bump('expense_categories');
        }
    }

    private function importProjects(Company $company): void
    {
        foreach ($this->source('projects')->where('is_deleted', 0)->get() as $row) {
            $project = Project::create([
                'company_id' => $company->id,
                'client_id' => $this->clientMap[$row->client_id] ?? null,
                'legacy_project_id' => $row->id,
                'name' => $row->name ?: 'Project #'.$row->id,
                'task_rate' => $row->task_rate,
                'budgeted_hours' => $row->budgeted_hours,
                'due_date' => $row->due_date,
                'notes' => $row->private_notes,
            ]);
            $this->projectMap[$row->id] = $project->id;
            $this->bump('projects');
        }
    }

    private function importTaskStatuses(Company $company): void
    {
        foreach ($this->source('task_statuses')->get() as $row) {
            $status = TaskStatus::create([
                'company_id' => $company->id,
                'legacy_task_status_id' => $row->id,
                'name' => $row->name,
                'sort_order' => $row->sort_order,
            ]);
            $this->taskStatusMap[$row->id] = $status->id;
            $this->bump('task_statuses');
        }
    }

    /**
     * v4's `time_log` is a JSON array of [start, end] second-timestamp
     * pairs; the target schema deliberately keeps only one segment per
     * task (see CLAUDE.md), so this takes the first start and the last
     * end as a reasonable single-segment approximation.
     */
    private function importTasks(Company $company): void
    {
        foreach ($this->source('tasks')->where('is_deleted', 0)->get() as $row) {
            [$startedAt, $stoppedAt] = $this->parseTimeLog($row->time_log);

            Task::create([
                'company_id' => $company->id,
                'project_id' => $this->projectMap[$row->project_id] ?? null,
                'client_id' => $this->clientMap[$row->client_id] ?? null,
                'invoice_id' => $this->invoiceMap[$row->invoice_id] ?? null,
                'task_status_id' => $this->taskStatusMap[$row->task_status_id] ?? null,
                'legacy_task_id' => $row->id,
                'description' => $row->description,
                'started_at' => $startedAt,
                'stopped_at' => $stoppedAt,
                'is_running' => (bool) $row->is_running,
                'sort_order' => $row->task_status_sort_order,
            ]);
            $this->bump('tasks');
        }
    }

    private function parseTimeLog(?string $timeLog): array
    {
        if (blank($timeLog)) {
            return [null, null];
        }

        $segments = json_decode($timeLog, true);

        if (! is_array($segments) || $segments === []) {
            return [null, null];
        }

        $start = $segments[0][0] ?? null;
        $end = collect($segments)->last()[1] ?? null;

        return [
            $start ? date('Y-m-d H:i:s', (int) $start) : null,
            $end ? date('Y-m-d H:i:s', (int) $end) : null,
        ];
    }

    private function importInvoices(Company $company): void
    {
        foreach ($this->source('invoices')->orderBy('id')->get() as $row) {
            // invoice_type_id: 1 = invoice, 2 = quote (confirmed against the
            // real dump: type=1 rows carry the "KJA/INV/..." number prefix).
            $type = ((int) $row->invoice_type_id) === 2 ? InvoiceType::Quote : InvoiceType::Invoice;

            $invoice = Invoice::create([
                'company_id' => $company->id,
                'client_id' => $this->clientMap[$row->client_id] ?? null,
                'legacy_invoice_id' => $row->id,
                'type' => $type,
                'status' => InvoiceStatus::Draft, // placeholder, set in finalizeInvoiceTotals()
                'number' => $row->invoice_number,
                'po_number' => $row->po_number,
                'invoice_date' => $row->invoice_date,
                'due_date' => $row->due_date,
                'currency_code' => $company->currency_code,
                'discount' => $row->discount ?? 0,
                'discount_is_percentage' => ! (bool) $row->is_amount_discount,
                'subtotal' => 0,
                'tax_total' => 0,
                'total' => 0,
                'amount_paid' => 0,
                'balance' => 0,
                'partial_amount' => $row->partial ?? 0,
                'partial_due_date' => $row->partial_due_date,
                'terms' => $row->terms,
                'public_notes' => $row->public_notes,
                'private_notes' => $row->private_notes,
                'footer' => $row->invoice_footer,
                'is_recurring' => (bool) $row->is_recurring,
                'auto_bill' => (bool) $row->auto_bill,
                'deleted_at' => $row->deleted_at ?? ($row->is_deleted ? $row->updated_at : null),
            ]);
            $this->invoiceMap[$row->id] = $invoice->id;
            $this->bump($type === InvoiceType::Quote ? 'quotes' : 'invoices');

            foreach ($this->source('invoice_items')->where('invoice_id', $row->id)->orderBy('id')->get() as $itemRow) {
                $lineGross = ((float) $itemRow->qty) * ((float) $itemRow->cost);
                $discount = $itemRow->discount ?? 0;
                $lineTotal = $this->money($lineGross - $discount);

                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $this->productMap[$itemRow->product_id] ?? null,
                    'legacy_invoice_item_id' => $itemRow->id,
                    'title' => $itemRow->product_key ?: 'Item',
                    'description' => $itemRow->notes,
                    'quantity' => $itemRow->qty ?: 1,
                    'unit_cost' => $itemRow->cost ?? 0,
                    'discount' => $discount,
                    'discount_is_percentage' => false,
                    'line_total' => $lineTotal,
                ]);
                $this->bump('invoice_items');

                foreach ([[$itemRow->tax_name1, $itemRow->tax_rate1], [$itemRow->tax_name2, $itemRow->tax_rate2]] as [$name, $rate]) {
                    if (filled($name) && (float) $rate > 0) {
                        $item->taxes()->create([
                            'tax_rate_id' => null,
                            'name' => $name,
                            'rate' => $rate,
                            'amount' => $this->money($lineTotal * ($rate / 100)),
                        ]);
                        $this->bump('invoice_item_taxes');
                    }
                }
            }

            // Client-portal invitations (one per contact the doc was shared
            // with) — preserves the historical `key` share-link token, and
            // rolls sent/viewed dates up onto the invoice itself (used by
            // resolveDocumentStatus() below to tell Draft from Sent/Viewed).
            $sentAt = null;
            $viewedAt = null;

            foreach ($this->source('invitations')->where('invoice_id', $row->id)->get() as $inviteRow) {
                if (! isset($this->contactMap[$inviteRow->contact_id])) {
                    continue;
                }

                Invitation::create([
                    'invoice_id' => $invoice->id,
                    'contact_id' => $this->contactMap[$inviteRow->contact_id],
                    'legacy_invitation_id' => $inviteRow->id,
                    'key' => $inviteRow->invitation_key,
                    'sent_at' => $inviteRow->sent_date,
                    'viewed_at' => $inviteRow->viewed_date,
                    'signed_at' => $inviteRow->signature_date,
                    'signature' => $inviteRow->signature_base64,
                ]);
                $this->bump('invitations');

                if ($inviteRow->sent_date && (! $sentAt || $inviteRow->sent_date < $sentAt)) {
                    $sentAt = $inviteRow->sent_date;
                }
                if ($inviteRow->viewed_date && (! $viewedAt || $inviteRow->viewed_date > $viewedAt)) {
                    $viewedAt = $inviteRow->viewed_date;
                }
            }

            if ($sentAt || $viewedAt) {
                $invoice->forceFill(['sent_at' => $sentAt, 'viewed_at' => $viewedAt])->saveQuietly();
            }

            if ($row->quote_id && isset($this->invoiceMap[$row->quote_id]) && $type === InvoiceType::Invoice) {
                $invoice->forceFill(['converted_from_quote_id' => $this->invoiceMap[$row->quote_id]])->saveQuietly();
            }
        }
    }

    private function importExpenses(Company $company): void
    {
        foreach ($this->source('expenses')->where('is_deleted', 0)->get() as $row) {
            $expense = Expense::create([
                'company_id' => $company->id,
                'vendor_id' => $this->vendorMap[$row->vendor_id] ?? null,
                'client_id' => $this->clientMap[$row->client_id] ?? null,
                'invoice_id' => $this->invoiceMap[$row->invoice_id] ?? null,
                'legacy_expense_id' => $row->id,
                'expense_date' => $row->expense_date,
                'currency_code' => $company->currency_code,
                'exchange_rate' => $row->exchange_rate ?: 1,
                'subtotal' => $this->money($row->amount),
                'should_be_invoiced' => (bool) $row->should_be_invoiced,
                'transaction_reference' => $row->transaction_reference,
                'private_notes' => $row->private_notes,
            ]);

            foreach ([[$row->tax_name1, $row->tax_rate1], [$row->tax_name2, $row->tax_rate2]] as [$name, $rate]) {
                if (filled($name) && (float) $rate > 0) {
                    $expense->taxes()->create([
                        'tax_rate_id' => null,
                        'name' => $name,
                        'rate' => $rate,
                        'amount' => $this->money($expense->subtotal * ($rate / 100)),
                    ]);
                }
            }

            app(ExpenseTotalsCalculator::class)->recalculate($expense);
            $this->bump('expenses');
        }
    }

    private function importCredits(Company $company): void
    {
        foreach ($this->source('credits')->where('is_deleted', 0)->get() as $row) {
            Credit::create([
                'company_id' => $company->id,
                'client_id' => $this->clientMap[$row->client_id] ?? null,
                'legacy_credit_id' => $row->id,
                'number' => $row->credit_number,
                'amount' => $this->money($row->amount),
                'balance' => $this->money($row->balance),
                'credit_date' => $row->credit_date,
                'public_notes' => $row->public_notes,
                'private_notes' => $row->private_notes,
            ]);
            $this->bump('credits');
        }
    }

    private function importPayments(Company $company): void
    {
        // payment_types is a global lookup table, not account-scoped.
        $paymentTypeNames = DB::connection($this->conn)->table('payment_types')->pluck('name', 'id');

        foreach ($this->source('payments')->where('is_deleted', 0)->get() as $row) {
            Payment::create([
                'company_id' => $company->id,
                'client_id' => $this->clientMap[$row->client_id] ?? null,
                'invoice_id' => $this->invoiceMap[$row->invoice_id] ?? null,
                'contact_id' => $this->contactMap[$row->contact_id] ?? null,
                'legacy_payment_id' => $row->id,
                'amount' => $this->money($row->amount),
                'refunded_amount' => $this->money($row->refunded ?? 0),
                'currency_code' => $company->currency_code,
                'method' => $paymentTypeNames[$row->payment_type_id] ?? null,
                'gateway_reference' => $row->transaction_reference,
                'status' => $this->mapLegacyPaymentStatus($row->payment_status_id),
                'last4' => $row->last4,
                'payment_date' => $row->payment_date,
                'notes' => $row->private_notes,
            ]);
            $this->bump('payments');
        }
    }

    /**
     * Sets each invoice's amount_paid from its actually-imported completed
     * payments, then reuses the app's own InvoiceTotalsCalculator so
     * subtotal/tax_total/total/balance/status are derived by the exact
     * same formula the rest of the app uses — not a separately hand-rolled
     * one that could drift from it.
     */
    private function finalizeInvoiceTotals(): void
    {
        $calculator = app(InvoiceTotalsCalculator::class);

        foreach ($this->invoiceMap as $invoice) {
            /** @var Invoice $invoice */
            $invoice = Invoice::withTrashed()->find($invoice);
            $paid = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', PaymentStatus::Completed)
                ->sum(DB::raw('amount - refunded_amount'));

            $invoice->forceFill(['amount_paid' => $this->money($paid)])->saveQuietly();
            $calculator->recalculate($invoice);

            $resolved = $this->resolveDocumentStatus(
                (float) $invoice->total,
                (float) $invoice->balance,
                (float) $invoice->amount_paid,
                $invoice->sent_at?->toDateTimeString(),
                $invoice->viewed_at?->toDateTimeString(),
            );
            $invoice->forceFill(['status' => $resolved['status']])->saveQuietly();
        }
    }

    private function bumpNumberingSequences(Company $company): void
    {
        $company->forceFill([
            'invoice_next_number' => Invoice::withTrashed()->where('company_id', $company->id)->where('type', InvoiceType::Invoice)->count() + 1,
            'quote_next_number' => Invoice::withTrashed()->where('company_id', $company->id)->where('type', InvoiceType::Quote)->count() + 1,
            'credit_next_number' => Credit::query()->where('company_id', $company->id)->count() + 1,
        ])->save();
    }

    private function reconcile(Company $company): void
    {
        $sourceTotal = $this->money($this->source('invoices')->sum('amount'));
        $targetTotal = $this->money(Invoice::withTrashed()->where('company_id', $company->id)->sum('total'));

        $sourcePayments = $this->money($this->source('payments')->where('is_deleted', 0)->sum('amount'));
        $targetPayments = $this->money(Payment::query()->where('company_id', $company->id)->sum('amount'));

        $this->info("Invoice+quote totals — source: {$sourceTotal}, recomputed from imported items: {$targetTotal}.");
        $this->info("Payments — source: {$sourcePayments}, imported: {$targetPayments}.");

        // A small gap is expected: totals are recomputed from items/discount
        // (App\Services\InvoiceTotalsCalculator), not copied from the
        // source's own `amount` column, so a handful of rows with
        // idiosyncratic historical rounding won't match to the cent.
        $tolerance = max(1.0, $sourceTotal * 0.001);

        if (abs($sourceTotal - $targetTotal) > $tolerance) {
            $this->warn('Invoice totals differ by more than 0.1% — investigate before trusting the import.');
        }

        if (abs($sourcePayments - $targetPayments) > 1.0) {
            $this->warn('Payment totals differ — investigate before trusting the import.');
        }
    }
}
