<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ImportsLegacyInvoiceNinja;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\MigrationBatchStatus;
use App\Enums\MigrationExceptionSeverity;
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
use App\Models\MigrationBatch;
use App\Models\MigrationException;
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
use App\Services\Migration\ReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports a legacy InvoiceNinja **v5** MySQL/MariaDB dump (invoices/quotes/
 * credits/recurring_invoices as separate tables, each carrying its own
 * `line_items` JSON blob rather than a shared `invoice_items` table) into
 * KapturInvoice's schema for one target Company. See docs/data-import.md.
 *
 * Reads from the `legacy_v5` connection (config/database.php) — point it
 * at a database you've already restored the dump into, e.g.:
 *   mysql -uroot legacy_v5 < axentech_ninj876.sql
 */
class ImportInvoiceNinjaV5 extends Command
{
    use ImportsLegacyInvoiceNinja;

    protected $signature = 'import:invoiceninja-v5
        {company : Target Company slug}
        {--legacy-company-id= : companies.id in the source dump to import (defaults to the only row present)}
        {--connection=legacy_v5 : Laravel DB connection name for the source dump}
        {--resume : Skip steps already completed by the most recent Failed batch for this company/connection}
        {--once : Refuse to run when this company/connection already has a completed migration batch}';

    protected $description = 'Import a legacy InvoiceNinja v5 dump into one KapturInvoice Company';

    private string $conn;

    private int $legacyCompanyId;

    /** @var array<int, int> */
    private array $productMap = [];

    /** @var array<int, int> */
    private array $clientMap = [];

    /** @var array<int, int> legacy client_contacts.id => new Contact id */
    private array $contactMap = [];

    /** @var array<int, int> */
    private array $vendorMap = [];

    /** @var array<int, int> */
    private array $projectMap = [];

    /** @var array<int, int> */
    private array $taskStatusMap = [];

    /** @var array<int, int> legacy invoices.id => new Invoice id (quotes/invoices share this map, keys never collide across the two source tables) */
    private array $invoiceIdMap = [];

    /** @var array<int, int> legacy quotes.id => new Invoice id */
    private array $quoteIdMap = [];

    private const SOURCE_SYSTEM = 'invoiceninja_v5';

    /**
     * Ordered so `--resume` can find "the step after the last completed
     * one" by array index — keep this in sync with the call sequence below.
     * `finalizeInvoiceTotals` takes no Company argument, unlike every other
     * step, hence the dispatch via {@see runStep()} rather than a plain
     * `$this->$step($company)` call.
     *
     * @var list<string>
     */
    private const STEPS = [
        'importTaxRates',
        'importProducts',
        'importClientsAndContacts',
        'importVendorsAndContacts',
        'importExpenseCategories',
        'importProjects',
        'importTaskStatuses',
        'importInvoices',
        'importQuotes',
        'importExpenses',
        'importCredits',
        'importPayments',
        'finalizeInvoiceTotals',
        'recomputeClientBalances',
        'bumpNumberingSequences',
    ];

    private MigrationBatch $batch;

    public function handle(): int
    {
        $company = Company::query()->where('slug', $this->argument('company'))->first();

        if (! $company) {
            $this->error("No company with slug [{$this->argument('company')}].");

            return self::FAILURE;
        }

        $this->conn = $this->option('connection');

        if ($this->option('once') && MigrationBatch::query()
            ->where('company_id', $company->id)
            ->where('source_system', self::SOURCE_SYSTEM)
            ->where('connection', $this->conn)
            ->where('status', MigrationBatchStatus::Completed)
            ->exists()) {
            $this->info("A completed InvoiceNinja v5 migration already exists for [{$this->argument('company')}] on connection [{$this->conn}]; nothing to do.");

            return self::SUCCESS;
        }

        if (! DB::connection($this->conn)->getSchemaBuilder()->hasTable('companies')) {
            $this->error("Connection [{$this->conn}] doesn't look like a restored InvoiceNinja v5 dump (no `companies` table).");

            return self::FAILURE;
        }

        $this->legacyCompanyId = (int) ($this->option('legacy-company-id')
            ?: DB::connection($this->conn)->table('companies')->value('id'));

        $skipUpToIndex = $this->openBatch($company);
        $this->rebuildMapsForSkippedSteps($company, $skipUpToIndex);

        try {
            foreach (self::STEPS as $index => $step) {
                if ($index <= $skipUpToIndex) {
                    continue;
                }

                DB::transaction(fn () => $this->runStep($step, $company));

                $this->batch->forceFill([
                    'last_completed_step' => $step,
                    'stats' => $this->importStats,
                ])->save();
            }
        } catch (Throwable $e) {
            $this->batch->forceFill([
                'status' => MigrationBatchStatus::Failed,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ])->save();

            $this->error("Import failed at step after [{$this->batch->last_completed_step}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->batch->forceFill([
            'status' => MigrationBatchStatus::Completed,
            'completed_at' => now(),
        ])->save();

        $this->printStats();
        $this->reconcile($company);

        return self::SUCCESS;
    }

    private function runStep(string $step, Company $company): void
    {
        if ($step === 'finalizeInvoiceTotals') {
            $this->finalizeInvoiceTotals();

            return;
        }

        $this->{$step}($company);
    }

    /**
     * Creates a fresh `Running` MigrationBatch, unless `--resume` is passed
     * and the most recent batch for this company/connection is `Failed` —
     * in which case that same row is reused (put back to `Running`) so its
     * history/stats aren't lost, and every step up to and including its
     * `last_completed_step` is skipped. Without `--resume`, a prior Failed
     * batch is always left alone and a brand-new one is started — resuming
     * is opt-in, never silent.
     *
     * @return int the STEPS index to resume after (-1 means "start from
     *             the first step")
     */
    private function openBatch(Company $company): int
    {
        if ($this->option('resume')) {
            $failed = MigrationBatch::query()
                ->where('company_id', $company->id)
                ->where('source_system', self::SOURCE_SYSTEM)
                ->where('connection', $this->conn)
                ->where('status', MigrationBatchStatus::Failed)
                ->latest('id')
                ->first();

            if ($failed) {
                $this->importStats = $failed->stats ?? [];

                $skipUpToIndex = $failed->last_completed_step
                    ? array_search($failed->last_completed_step, self::STEPS, true)
                    : -1;

                $failed->forceFill([
                    'status' => MigrationBatchStatus::Running,
                    'error_message' => null,
                    'failed_at' => null,
                ])->save();

                $this->batch = $failed;
                $this->info("Resuming batch #{$failed->id} after step [{$failed->last_completed_step}].");

                return $skipUpToIndex === false ? -1 : $skipUpToIndex;
            }

            $this->warn('No Failed batch found to resume — starting a fresh one.');
        }

        $this->batch = MigrationBatch::create([
            'company_id' => $company->id,
            'source_system' => self::SOURCE_SYSTEM,
            'connection' => $this->conn,
            'status' => MigrationBatchStatus::Running,
            'stats' => [],
            'started_at' => now(),
        ]);

        return -1;
    }

    /**
     * On `--resume`, the steps up through `$skipUpToIndex` never run this
     * invocation — but every later step still reads the in-memory
     * `*Map` properties those steps would normally have populated (e.g.
     * `importPayments` needs `clientMap`/`invoiceIdMap` from steps that ran
     * in the *previous*, failed invocation). Rebuild each affected map from
     * what's already in the database (keyed by the same legacy id columns
     * `updateOrCreate` matches on) rather than leaving it empty, which
     * would otherwise silently null out every relation on a resumed run.
     */
    private function rebuildMapsForSkippedSteps(Company $company, int $skipUpToIndex): void
    {
        if ($skipUpToIndex < 0) {
            return;
        }

        $stepIndex = fn (string $step): int => array_search($step, self::STEPS, true);

        if ($skipUpToIndex >= $stepIndex('importProducts')) {
            $this->productMap = Product::query()->where('company_id', $company->id)
                ->whereNotNull('legacy_product_id')->pluck('id', 'legacy_product_id')->all();
        }

        if ($skipUpToIndex >= $stepIndex('importClientsAndContacts')) {
            $this->clientMap = Client::withTrashed()->where('company_id', $company->id)
                ->whereNotNull('legacy_client_id')->pluck('id', 'legacy_client_id')->all();
            $this->contactMap = Contact::withTrashed()->whereHas('client', fn ($q) => $q->where('company_id', $company->id))
                ->whereNotNull('legacy_contact_id')->pluck('id', 'legacy_contact_id')->all();
        }

        if ($skipUpToIndex >= $stepIndex('importVendorsAndContacts')) {
            $this->vendorMap = Vendor::withTrashed()->where('company_id', $company->id)
                ->whereNotNull('legacy_vendor_id')->pluck('id', 'legacy_vendor_id')->all();
        }

        if ($skipUpToIndex >= $stepIndex('importProjects')) {
            $this->projectMap = Project::query()->where('company_id', $company->id)
                ->whereNotNull('legacy_project_id')->pluck('id', 'legacy_project_id')->all();
        }

        if ($skipUpToIndex >= $stepIndex('importTaskStatuses')) {
            $this->taskStatusMap = TaskStatus::query()->where('company_id', $company->id)
                ->whereNotNull('legacy_task_status_id')->pluck('id', 'legacy_task_status_id')->all();
        }

        if ($skipUpToIndex >= $stepIndex('importInvoices')) {
            $this->invoiceIdMap = Invoice::withTrashed()->where('company_id', $company->id)
                ->where('type', InvoiceType::Invoice)->whereNotNull('legacy_invoice_id')
                ->pluck('id', 'legacy_invoice_id')->all();
        }

        if ($skipUpToIndex >= $stepIndex('importQuotes')) {
            $this->quoteIdMap = Invoice::withTrashed()->where('company_id', $company->id)
                ->where('type', InvoiceType::Quote)->whereNotNull('legacy_invoice_id')
                ->pluck('id', 'legacy_invoice_id')->all();
        }
    }

    private function source(string $table)
    {
        return DB::connection($this->conn)->table($table)->where('company_id', $this->legacyCompanyId);
    }

    /**
     * Keyed on `(company_id, legacy_tax_rate_id)` (and the equivalent for
     * every other entity below) so re-running the same step — either a
     * plain re-run or a `--resume` after a later step failed — updates the
     * same row instead of creating a duplicate. See the STEPS docblock:
     * "re-running a batch creates no duplicates" is a Phase 07 requirement.
     */
    private function importTaxRates(Company $company): void
    {
        foreach ($this->source('tax_rates')->where('is_deleted', 0)->get() as $row) {
            TaxRate::updateOrCreate(
                ['company_id' => $company->id, 'legacy_tax_rate_id' => $row->id],
                ['name' => $row->name, 'rate' => $row->rate, 'is_inclusive' => false],
            );
            $this->bump('tax_rates');
        }
    }

    private function importProducts(Company $company): void
    {
        foreach ($this->source('products')->where('is_deleted', 0)->get() as $row) {
            $product = Product::updateOrCreate(
                ['company_id' => $company->id, 'legacy_product_id' => $row->id],
                [
                    'sku' => $row->product_key
                        ? mb_substr((string) $row->product_key, 0, 255)
                        : null,
                    // `price` is the sale price actually billed on invoices;
                    // `cost` is an (unused, always 0 in this dataset) internal
                    // cost-basis field — deliberately not imported as unit_cost.
                    // Some real Invoice Ninja v5 dumps put the complete
                    // product description in `notes` (well over 255 chars).
                    // Keep the full source text in `description`, but keep
                    // the canonical product name within the target column.
                    'name' => mb_substr((string) ($row->product_key ?: $row->notes ?: 'Product'), 0, 255),
                    'description' => $row->notes,
                    'unit_cost' => $row->price ?? 0,
                ],
            );
            $this->productMap[$row->id] = $product->id;
            $this->bump('products');
        }
    }

    private function importClientsAndContacts(Company $company): void
    {
        foreach ($this->source('clients')->get() as $row) {
            $client = Client::updateOrCreate(
                ['company_id' => $company->id, 'legacy_client_id' => $row->id],
                [
                    'name' => $row->name ?: 'Client #'.$row->id,
                    'currency_code' => $company->currency_code,
                    'phone' => $row->phone,
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
                    'deleted_at' => $row->deleted_at,
                ],
            );
            $this->clientMap[$row->id] = $client->id;
            $this->bump('clients');

            $primaryEmail = null;

            foreach (DB::connection($this->conn)->table('client_contacts')->where('client_id', $row->id)->get() as $contactRow) {
                $contact = Contact::updateOrCreate(
                    ['client_id' => $client->id, 'legacy_contact_id' => $contactRow->id],
                    [
                        'first_name' => $contactRow->first_name ?: 'Contact',
                        'last_name' => $contactRow->last_name,
                        'email' => $contactRow->email ?: null,
                        'phone' => $contactRow->phone,
                        'is_primary' => (bool) $contactRow->is_primary,
                    ],
                );
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
        foreach ($this->source('vendors')->get() as $row) {
            $vendor = Vendor::updateOrCreate(
                ['company_id' => $company->id, 'legacy_vendor_id' => $row->id],
                [
                    'name' => $row->name ?: 'Vendor #'.$row->id,
                    'phone' => $row->phone,
                    'website' => $row->website,
                    'address_line_1' => $row->address1,
                    'address_line_2' => $row->address2,
                    'city' => $row->city,
                    'state' => $row->state,
                    'postal_code' => $row->postal_code,
                    'notes' => $row->private_notes,
                    'deleted_at' => $row->deleted_at,
                ],
            );
            $this->vendorMap[$row->id] = $vendor->id;
            $this->bump('vendors');

            foreach (DB::connection($this->conn)->table('vendor_contacts')->where('vendor_id', $row->id)->get() as $contactRow) {
                VendorContact::updateOrCreate(
                    ['vendor_id' => $vendor->id, 'legacy_vendor_contact_id' => $contactRow->id],
                    [
                        'first_name' => $contactRow->first_name ?: 'Contact',
                        'last_name' => $contactRow->last_name,
                        'email' => $contactRow->email ?: null,
                        'phone' => $contactRow->phone,
                        'is_primary' => (bool) $contactRow->is_primary,
                    ],
                );
                $this->bump('vendor_contacts');
            }
        }
    }

    private function importExpenseCategories(Company $company): void
    {
        foreach ($this->source('expense_categories')->where('is_deleted', 0)->get() as $row) {
            ExpenseCategory::updateOrCreate(
                ['company_id' => $company->id, 'legacy_expense_category_id' => $row->id],
                ['name' => $row->name],
            );
            $this->bump('expense_categories');
        }
    }

    private function importProjects(Company $company): void
    {
        foreach ($this->source('projects')->where('is_deleted', 0)->get() as $row) {
            $project = Project::updateOrCreate(
                ['company_id' => $company->id, 'legacy_project_id' => $row->id],
                [
                    'client_id' => $this->clientMap[$row->client_id] ?? null,
                    'name' => $row->name ?: 'Project #'.$row->id,
                    'task_rate' => $row->task_rate,
                    'budgeted_hours' => $row->budgeted_hours,
                    'due_date' => $row->due_date,
                    'notes' => $row->private_notes,
                ],
            );
            $this->projectMap[$row->id] = $project->id;
            $this->bump('projects');
        }
    }

    private function importTaskStatuses(Company $company): void
    {
        foreach ($this->source('task_statuses')->where('is_deleted', 0)->get() as $row) {
            $status = TaskStatus::updateOrCreate(
                ['company_id' => $company->id, 'legacy_task_status_id' => $row->id],
                ['name' => $row->name, 'sort_order' => $row->status_order ?? $row->status_sort_order ?? 0],
            );
            $this->taskStatusMap[$row->id] = $status->id;
            $this->bump('task_statuses');
        }

        // No `tasks` rows exist in the one real v5 dump this was built
        // against (a fresh install with no time-tracking usage yet), so
        // task import itself is intentionally not implemented here — see
        // ImportInvoiceNinjaV4 for the pattern (time_log JSON -> a single
        // started_at/stopped_at segment) if a v5 source ever has any.
        if ($this->source('tasks')->exists()) {
            $this->warn('This dump has `tasks` rows, which this v5 importer does not yet migrate — see the docblock above importTaskStatuses().');
        }
    }

    /**
     * v5 stores each invoice/quote/credit's line items as its own
     * `line_items` JSON column rather than a shared item table — decode it
     * into the same shape used across every entity type.
     *
     * Most real invoices in this dump don't set tax on the line item at
     * all — tax is applied once at the *invoice* level
     * (`invoices.tax_name1`/`tax_rate1`, occasionally `uses_inclusive_taxes`
     * meaning the item's own `cost` already has that tax baked in). The
     * target schema has no invoice-level tax column (tax is only ever a
     * per-item `invoice_item_taxes` row — see CLAUDE.md's "Normalized tax
     * pivots"), so a header-level tax with no matching per-item tax is
     * pushed down onto every item here, extracting it back out of an
     * already-tax-inclusive `cost` rather than adding it on top when
     * `uses_inclusive_taxes` says the line total already includes it —
     * otherwise the invoice's `total` would come out ~11% too high.
     *
     * @return array<int, array{title: string, description: ?string, quantity: float, unit_cost: float, discount: float, is_amount_discount: bool, inclusive_tax_rate: ?float, taxes: array<int, array{name: string, rate: float}>}>
     */
    private function decodeLineItems(?string $json, ?object $parentRow = null): array
    {
        $items = json_decode((string) $json, true);

        if (! is_array($items)) {
            return [];
        }

        $headerTax = null;
        if ($parentRow && filled($parentRow->tax_name1 ?? null) && (float) ($parentRow->tax_rate1 ?? 0) > 0) {
            $headerTax = ['name' => $parentRow->tax_name1, 'rate' => (float) $parentRow->tax_rate1];
        }
        $headerTaxIsInclusive = (bool) ($parentRow->uses_inclusive_taxes ?? false);

        return collect($items)->map(function (array $item) use ($headerTax, $headerTaxIsInclusive) {
            $taxes = [];

            foreach ([[$item['tax_name1'] ?? null, $item['tax_rate1'] ?? 0], [$item['tax_name2'] ?? null, $item['tax_rate2'] ?? 0], [$item['tax_name3'] ?? null, $item['tax_rate3'] ?? 0]] as [$name, $rate]) {
                if (filled($name) && (float) $rate > 0) {
                    $taxes[] = ['name' => $name, 'rate' => (float) $rate];
                }
            }

            $inclusiveTaxRate = null;

            if ($taxes === [] && $headerTax) {
                if ($headerTaxIsInclusive) {
                    $inclusiveTaxRate = $headerTax['rate'];
                    $taxes[] = $headerTax;
                } else {
                    $taxes[] = $headerTax;
                }
            }

            $fallbackTitle = trim((string) ($item['product_key'] ?: $item['notes'] ?: 'Item'));
            $fallbackTitle = preg_split('/\R/', $fallbackTitle, 2)[0] ?: 'Item';

            return [
                'title' => mb_substr($fallbackTitle, 0, 255),
                'description' => $item['notes'] ?? null,
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_cost' => (float) ($item['cost'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'is_amount_discount' => (bool) ($item['is_amount_discount'] ?? false),
                'inclusive_tax_rate' => $inclusiveTaxRate,
                'taxes' => $taxes,
            ];
        })->all();
    }

    /**
     * Deletes any items this invoice/quote already has (its own tax pivot
     * rows cascade with them) before recreating from the source dump — the
     * source has no per-item legacy id to `updateOrCreate` against, and a
     * re-run/resume must not double up line items on an invoice that
     * already existed from a prior run.
     */
    private function createInvoiceItems(Invoice $invoice, array $lineItems): void
    {
        InvoiceItem::query()->where('invoice_id', $invoice->id)->get()->each(function (InvoiceItem $item) {
            $item->taxes()->delete();
            $item->delete();
        });

        foreach ($lineItems as $lineItem) {
            $unitCost = $lineItem['unit_cost'];

            // An inclusive header tax means $unitCost already has it baked
            // in — divide it back out (App\Services\InvoiceTotalsCalculator
            // recomputes line_total itself from quantity*unit_cost, so it's
            // unit_cost that must be tax-exclusive, not just line_total,
            // or the tax ends up counted twice).
            if ($lineItem['inclusive_tax_rate']) {
                $unitCost = $unitCost / (1 + $lineItem['inclusive_tax_rate'] / 100);
            }

            $lineGross = $lineItem['quantity'] * $unitCost;
            $discount = $lineItem['is_amount_discount']
                ? $lineItem['discount']
                : $lineGross * ($lineItem['discount'] / 100);
            $lineTotal = $this->money($lineGross - $discount);

            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'title' => $lineItem['title'],
                'description' => $lineItem['description'],
                'quantity' => $lineItem['quantity'],
                'unit_cost' => $unitCost,
                // Preserve the discount in whatever shape the source used —
                // App\Services\InvoiceTotalsCalculator::recalculate() derives
                // line_total fresh from quantity/unit_cost/discount(_is_percentage)
                // every time it runs (e.g. from finalizeInvoiceTotals() below),
                // so storing a flat 0 here for a percentage discount would
                // silently discard it on the very next recalculation.
                'discount' => $lineItem['discount'],
                'discount_is_percentage' => ! $lineItem['is_amount_discount'],
                'line_total' => $lineTotal,
            ]);
            $this->bump('invoice_items');

            foreach ($lineItem['taxes'] as $tax) {
                $item->taxes()->create([
                    'tax_rate_id' => null,
                    'name' => $tax['name'],
                    'rate' => $tax['rate'],
                    'amount' => $this->money($lineTotal * ($tax['rate'] / 100)),
                ]);
                $this->bump('invoice_item_taxes');
            }
        }
    }

    private function importInvoices(Company $company): void
    {
        foreach ($this->source('invoices')->orderBy('id')->get() as $row) {
            $invoice = Invoice::updateOrCreate(
                ['company_id' => $company->id, 'legacy_invoice_id' => $row->id, 'type' => InvoiceType::Invoice],
                [
                    'client_id' => $this->clientMap[$row->client_id] ?? null,
                    'status' => InvoiceStatus::Draft, // placeholder, set in finalizeInvoiceTotals()
                    'number' => $row->number,
                    'po_number' => $row->po_number,
                    'invoice_date' => $row->date,
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
                    'footer' => $row->footer,
                    'is_recurring' => false,
                    'auto_bill' => (bool) $row->auto_bill_enabled,
                    'deleted_at' => $row->deleted_at,
                ],
            );
            $this->invoiceIdMap[$row->id] = $invoice->id;
            $this->bump('invoices');

            $this->createInvoiceItems($invoice, $this->decodeLineItems($row->line_items, $row));

            $sentAt = null;
            $viewedAt = null;

            foreach (DB::connection($this->conn)->table('invoice_invitations')->where('invoice_id', $row->id)->get() as $inviteRow) {
                if (! isset($this->contactMap[$inviteRow->client_contact_id])) {
                    continue;
                }

                Invitation::updateOrCreate(
                    ['invoice_id' => $invoice->id, 'legacy_invitation_id' => $inviteRow->id],
                    [
                        'contact_id' => $this->contactMap[$inviteRow->client_contact_id],
                        'key' => $inviteRow->key,
                        'sent_at' => $inviteRow->sent_date,
                        'viewed_at' => $inviteRow->viewed_date,
                        'signed_at' => $inviteRow->signature_date,
                        'signature' => $inviteRow->signature_base64,
                    ],
                );
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
        }
    }

    private function importQuotes(Company $company): void
    {
        foreach ($this->source('quotes')->orderBy('id')->get() as $row) {
            $quote = Invoice::updateOrCreate(
                // `legacy_invoice_id` isn't unique-constrained, so it's fine
                // that quotes.id and invoices.id ranges can overlap (they're
                // separate source tables) — `type` disambiguates the two
                // when looking a row back up on a re-run/resume.
                ['company_id' => $company->id, 'legacy_invoice_id' => $row->id, 'type' => InvoiceType::Quote],
                [
                    'client_id' => $this->clientMap[$row->client_id] ?? null,
                    'status' => InvoiceStatus::Draft,
                    'number' => $row->number,
                    'po_number' => $row->po_number,
                    'invoice_date' => $row->date,
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
                    'footer' => $row->footer,
                    'is_recurring' => false,
                    'deleted_at' => $row->deleted_at,
                ],
            );
            $this->quoteIdMap[$row->id] = $quote->id;
            $this->bump('quotes');

            $this->createInvoiceItems($quote, $this->decodeLineItems($row->line_items, $row));

            foreach (DB::connection($this->conn)->table('quote_invitations')->where('quote_id', $row->id)->get() as $inviteRow) {
                if (! isset($this->contactMap[$inviteRow->client_contact_id])) {
                    continue;
                }

                Invitation::updateOrCreate(
                    ['invoice_id' => $quote->id, 'legacy_invitation_id' => $inviteRow->id],
                    [
                        'contact_id' => $this->contactMap[$inviteRow->client_contact_id],
                        'key' => $inviteRow->key,
                        'sent_at' => $inviteRow->sent_date,
                        'viewed_at' => $inviteRow->viewed_date,
                        'signed_at' => $inviteRow->signature_date,
                        'signature' => $inviteRow->signature_base64,
                    ],
                );
                $this->bump('invitations');

                if ($inviteRow->sent_date || $inviteRow->viewed_date) {
                    $quote->forceFill([
                        'sent_at' => $quote->sent_at ?? $inviteRow->sent_date,
                        'viewed_at' => $inviteRow->viewed_date ?? $quote->viewed_at,
                    ])->saveQuietly();
                }
            }

            // v5's `quotes.invoice_id` points at the invoice this quote was
            // converted into, if any.
            if ($row->invoice_id && isset($this->invoiceIdMap[$row->invoice_id])) {
                Invoice::withTrashed()->whereKey($this->invoiceIdMap[$row->invoice_id])
                    ->update(['converted_from_quote_id' => $quote->id]);
            }
        }
    }

    private function importExpenses(Company $company): void
    {
        foreach ($this->source('expenses')->where('is_deleted', 0)->get() as $row) {
            $expense = Expense::updateOrCreate(
                ['company_id' => $company->id, 'legacy_expense_id' => $row->id],
                [
                    'vendor_id' => $this->vendorMap[$row->vendor_id] ?? null,
                    'client_id' => $this->clientMap[$row->client_id] ?? null,
                    'invoice_id' => $this->invoiceIdMap[$row->invoice_id] ?? null,
                    'expense_date' => $row->date,
                    'currency_code' => $company->currency_code,
                    'exchange_rate' => $row->exchange_rate ?: 1,
                    'subtotal' => $this->money($row->amount),
                    'should_be_invoiced' => (bool) $row->should_be_invoiced,
                    'transaction_reference' => $row->transaction_reference,
                    'private_notes' => $row->private_notes,
                ],
            );

            // No legacy id to `updateOrCreate` a tax pivot row against —
            // drop and recreate from the source dump every time, same
            // reasoning as createInvoiceItems() above.
            $expense->taxes()->delete();

            foreach ([[$row->tax_name1, $row->tax_rate1], [$row->tax_name2, $row->tax_rate2], [$row->tax_name3, $row->tax_rate3]] as [$name, $rate]) {
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

    /**
     * Per Phase 07 Specs.md: "Import confidently mapped historical credits
     * as read-only records that reduce the applicable balance. Quarantine
     * uncertain credits and exclude them from confirmed balances until
     * Owner review." Data is never invented or silently dropped: a
     * balance-anomaly credit is still imported in full (its client mapped
     * fine) alongside its `MigrationException`. An unmapped-client credit
     * is the one case that can't literally be inserted as a real `Credit`
     * row — `credits.client_id` is a NOT NULL/constrained foreign key (same
     * as invoices/payments), so there's no non-inventive value to put
     * there — this is quarantined (High) and the row is skipped rather
     * than fabricating a client to satisfy the constraint; the exception
     * itself preserves every original field for Owner review.
     */
    private function importCredits(Company $company): void
    {
        foreach ($this->source('credits')->where('is_deleted', 0)->get() as $row) {
            $clientId = $this->clientMap[$row->client_id] ?? null;

            if ($clientId === null) {
                $this->quarantineCredit(
                    $company, $row->id,
                    MigrationExceptionSeverity::High,
                    "Credit #{$row->id} (amount {$row->amount}, balance {$row->balance}) references legacy client_id [{$row->client_id}], which did not map to an imported Client — cannot be inserted (client_id is a required foreign key) or confidently tied to a customer balance. Not imported; needs Owner review.",
                );

                continue;
            }

            Credit::updateOrCreate(
                ['company_id' => $company->id, 'legacy_credit_id' => $row->id],
                [
                    'client_id' => $clientId,
                    'number' => $row->number,
                    'amount' => $this->money($row->amount),
                    'balance' => $this->money($row->balance),
                    'credit_date' => $row->date,
                    'public_notes' => $row->public_notes,
                    'private_notes' => $row->private_notes,
                    'deleted_at' => $row->deleted_at,
                ],
            );
            $this->bump('credits');

            if ((float) $row->balance > (float) $row->amount) {
                $this->quarantineCredit(
                    $company, $row->id,
                    MigrationExceptionSeverity::Medium,
                    "Credit #{$row->id}'s recorded balance ({$row->balance}) exceeds its original amount ({$row->amount}) — data-integrity anomaly in the source dump.",
                );
            }
        }
    }

    private function quarantineCredit(Company $company, int $sourceId, MigrationExceptionSeverity $severity, string $reason): void
    {
        MigrationException::updateOrCreate(
            ['company_id' => $company->id, 'entity_type' => 'credit', 'source_id' => $sourceId],
            ['migration_batch_id' => $this->batch->id, 'severity' => $severity, 'reason' => $reason],
        );
        $this->bump('credit_exceptions');
    }

    /**
     * Each real payment in this dump applies to at most one invoice via
     * `paymentables` (paymentable_type='invoices') — a payment with no
     * paymentables row was never actually applied (voided/failed). A
     * payment split across several invoices doesn't occur in the source
     * data this was built against; if one ever does, only the first
     * paymentable is used and the rest are logged as a warning rather than
     * silently dropping money.
     */
    private function importPayments(Company $company): void
    {
        foreach ($this->source('payments')->where('is_deleted', 0)->get() as $row) {
            $paymentables = DB::connection($this->conn)->table('paymentables')
                ->where('payment_id', $row->id)
                ->where('paymentable_type', 'invoices')
                ->get();

            $invoiceId = $paymentables->isNotEmpty()
                ? ($this->invoiceIdMap[$paymentables->first()->paymentable_id] ?? null)
                : null;

            if ($paymentables->count() > 1) {
                $this->warn("Payment #{$row->id} applies to multiple invoices — only the first was linked; amount is still recorded in full.");
            }

            Payment::updateOrCreate(
                ['company_id' => $company->id, 'legacy_payment_id' => $row->id],
                [
                    'client_id' => $this->clientMap[$row->client_id] ?? null,
                    'invoice_id' => $invoiceId,
                    'amount' => $this->money($row->amount),
                    'refunded_amount' => $this->money($row->refunded ?? 0),
                    'currency_code' => $company->currency_code,
                    // `method` deliberately left blank: v5's `payments.type_id`
                    // has no lookup table in the dump (baked into the app's own
                    // code, unlike v4's `payment_types` table), and every real
                    // payment in this dump shares the same type_id — not enough
                    // to safely infer what it means without risking a wrong
                    // label on real historical payment methods.
                    'gateway_reference' => $row->transaction_reference,
                    'status' => $this->mapLegacyPaymentStatus($row->status_id),
                    'payment_date' => $row->date,
                    'notes' => $row->private_notes,
                ],
            );
            $this->bump('payments');
        }
    }

    private function finalizeInvoiceTotals(): void
    {
        $calculator = app(InvoiceTotalsCalculator::class);

        foreach ([...$this->invoiceIdMap, ...$this->quoteIdMap] as $invoiceId) {
            $invoice = Invoice::withTrashed()->find($invoiceId);
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
        $sourceTotals = [
            // Every source row is imported regardless of is_deleted — the
            // row's own soft-delete state maps to the target's deleted_at
            // instead of being excluded (see importClientsAndContacts()).
            'clients' => $this->source('clients')->count(),
            'vendors' => $this->source('vendors')->count(),
            'invoice_total' => $this->money(
                $this->source('invoices')->sum('amount') + $this->source('quotes')->sum('amount')
            ),
            'payment_total' => $this->money($this->source('payments')->where('is_deleted', 0)->sum('amount')),
        ];

        $run = app(ReconciliationService::class)->reconcile($company, $this->batch, $sourceTotals);

        $this->info("Reconciliation status: {$run->status->value}.");
        foreach ($run->metrics['checks'] as $name => $check) {
            if (! $check['within_tolerance']) {
                $this->warn("{$name} differs beyond tolerance — source {$check['source']}, imported {$check['target']} (diff {$check['diff']}).");
            }
        }
        if ($run->metrics['exceptions']['high_unresolved'] > 0) {
            $this->warn("{$run->metrics['exceptions']['high_unresolved']} unresolved High-severity exception(s) — resolve before business sign-off.");
        }
    }
}
