<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\MigrationBatch;
use App\Services\Migration\ReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-runs reconciliation against a company's already-imported data without
 * repeating the full `import:invoiceninja-v4`/`-v5` run — Phase 07's
 * cutover sequence needs this standalone ("Reconcile again" after
 * resolving exceptions, and a "next-business-day post-cutover
 * reconciliation") — see
 * docs/rebuild/specs/07-migration-and-cutover/Specs.md. Reads the same
 * source dump connection the original import used, so the source
 * database must still be reachable (kept read-only, per the spec, not
 * deleted after cutover).
 */
class ReconcileMigration extends Command
{
    protected $signature = 'migration:reconcile
        {company : Target Company slug}
        {--source= : Which legacy schema to compare against: v4 or v5}
        {--legacy-account-id=1 : accounts.id in the source dump (v4 only)}
        {--legacy-company-id= : companies.id in the source dump (v5 only, defaults to the only row present)}
        {--connection= : Laravel DB connection name for the source dump (defaults to legacy_v4/legacy_v5)}';

    protected $description = "Re-run Phase 07 reconciliation for a company's already-imported data, without a full re-import";

    public function handle(ReconciliationService $reconciliationService): int
    {
        $company = Company::where('slug', $this->argument('company'))->firstOrFail();

        $source = $this->option('source');
        if (! in_array($source, ['v4', 'v5'], true)) {
            $this->error('--source must be "v4" or "v5".');

            return self::FAILURE;
        }

        $conn = $this->option('connection') ?: "legacy_{$source}";
        $sourceSystem = "invoiceninja_{$source}";

        $batch = MigrationBatch::where('company_id', $company->id)
            ->where('source_system', $sourceSystem)
            ->latest('id')
            ->first();

        $sourceTotals = $source === 'v4'
            ? $this->v4Totals($conn)
            : $this->v5Totals($conn);

        $run = $reconciliationService->reconcile($company, $batch, $sourceTotals);

        $this->info("Reconciliation status: {$run->status->value}.");
        foreach ($run->metrics['checks'] as $name => $check) {
            $line = "{$name} — source: {$check['source']}, imported: {$check['target']}";
            if ($check['within_tolerance']) {
                $this->line($line);
            } else {
                $this->warn("{$line} (diff {$check['diff']}, beyond tolerance {$check['tolerance']})");
            }
        }
        if ($run->metrics['exceptions']['high_unresolved'] > 0) {
            $this->warn("{$run->metrics['exceptions']['high_unresolved']} unresolved High-severity exception(s) — resolve before business sign-off.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, float|int>
     */
    private function v4Totals(string $conn): array
    {
        $accountId = (int) $this->option('legacy-account-id');
        $source = fn (string $table) => DB::connection($conn)->table($table)->where('account_id', $accountId);

        return [
            'clients' => $source('clients')->count(),
            'vendors' => $source('vendors')->where('is_deleted', 0)->count(),
            'invoice_total' => round((float) $source('invoices')->sum('amount'), 2),
            'payment_total' => round((float) $source('payments')->where('is_deleted', 0)->sum('amount'), 2),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function v5Totals(string $conn): array
    {
        $legacyCompanyId = (int) ($this->option('legacy-company-id') ?: DB::connection($conn)->table('companies')->value('id'));
        $source = fn (string $table) => DB::connection($conn)->table($table)->where('company_id', $legacyCompanyId);

        return [
            'clients' => $source('clients')->count(),
            'vendors' => $source('vendors')->count(),
            'invoice_total' => round((float) ($source('invoices')->sum('amount') + $source('quotes')->sum('amount')), 2),
            'payment_total' => round((float) $source('payments')->where('is_deleted', 0)->sum('amount'), 2),
        ];
    }
}
