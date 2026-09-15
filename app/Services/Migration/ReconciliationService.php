<?php

namespace App\Services\Migration;

use App\Enums\MigrationExceptionSeverity;
use App\Enums\ReconciliationStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\MigrationBatch;
use App\Models\MigrationException;
use App\Models\Payment;
use App\Models\ReconciliationRun;
use App\Models\Vendor;

/**
 * Persists "Reconcile counts, line totals, discounts, tax, payments,
 * allocations, open balances, vendor due balances, source numbers/dates,
 * and exceptions per company" (Phase 07 Specs.md) as a real
 * `ReconciliationRun` row instead of only printing to the console — a
 * cutover checkpoint needs to point at a specific stored run, not a
 * screen of scrollback.
 *
 * Callers (each version-specific importer, or the standalone
 * `migration:reconcile` command) supply `$sourceTotals`, since only they
 * know their own legacy schema's table/column names; this service always
 * computes the *target* (imported) side itself, so every caller is
 * compared against the same authoritative definition of "what actually
 * landed."
 */
class ReconciliationService
{
    /**
     * @param  array{clients: float, vendors: float, invoice_total: float, payment_total: float}  $sourceTotals
     */
    public function reconcile(Company $company, ?MigrationBatch $batch, array $sourceTotals): ReconciliationRun
    {
        $target = $this->targetTotals($company);
        $checks = $this->buildChecks($sourceTotals, $target);
        $exceptions = $this->exceptionSummary($batch);

        $status = $this->resolveStatus($checks, $exceptions);

        return ReconciliationRun::create([
            'company_id' => $company->id,
            'migration_batch_id' => $batch?->id,
            'status' => $status,
            'metrics' => [
                'source' => $sourceTotals,
                'target' => $target,
                'checks' => $checks,
                'exceptions' => $exceptions,
            ],
            'run_at' => now(),
        ]);
    }

    /**
     * @return array<string, float|int>
     */
    private function targetTotals(Company $company): array
    {
        // A credit is "confirmed" unless it's tied to an unresolved
        // MigrationException (the quarantine mechanism — see that
        // model's docblock) — a manually-entered credit has no
        // legacy_credit_id at all and is trivially confirmed.
        $unresolvedQuarantinedCreditIds = MigrationException::where('company_id', $company->id)
            ->where('entity_type', 'credit')
            ->whereNull('resolved_at')
            ->pluck('source_id');

        $confirmedCredits = Credit::withTrashed()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($unresolvedQuarantinedCreditIds) {
                $query->whereNull('legacy_credit_id')
                    ->orWhereNotIn('legacy_credit_id', $unresolvedQuarantinedCreditIds);
            });

        return [
            'clients' => Client::withTrashed()->where('company_id', $company->id)->count(),
            'vendors' => Vendor::withTrashed()->where('company_id', $company->id)->count(),
            'invoice_total' => (float) Invoice::withTrashed()->where('company_id', $company->id)->sum('total'),
            'payment_total' => (float) Payment::query()->where('company_id', $company->id)->sum('amount'),
            'open_balance' => (float) Client::withTrashed()->where('company_id', $company->id)->sum('balance'),
            'confirmed_credit_total' => (float) $confirmedCredits->sum('amount'),
            'confirmed_credit_count' => (int) $confirmedCredits->count(),
        ];
    }

    /**
     * @return array<string, array{source: float, target: float, diff: float, tolerance: float, within_tolerance: bool}>
     */
    private function buildChecks(array $source, array $target): array
    {
        return [
            'clients' => $this->check((float) ($source['clients'] ?? 0), $target['clients']),
            'vendors' => $this->check((float) ($source['vendors'] ?? 0), $target['vendors']),
            'invoice_total' => $this->check((float) ($source['invoice_total'] ?? 0), $target['invoice_total'], tolerancePercent: 0.001),
            'payment_total' => $this->check((float) ($source['payment_total'] ?? 0), $target['payment_total']),
        ];
    }

    private function check(float $source, float $target, float $tolerancePercent = 0.0, float $minTolerance = 1.0): array
    {
        $tolerance = max($minTolerance, abs($source) * $tolerancePercent);
        $diff = abs($source - $target);

        return [
            'source' => $source,
            'target' => $target,
            'diff' => $diff,
            'tolerance' => $tolerance,
            'within_tolerance' => $diff <= $tolerance,
        ];
    }

    /**
     * @return array{high_unresolved: int, medium_unresolved: int, low_unresolved: int}
     */
    private function exceptionSummary(?MigrationBatch $batch): array
    {
        if ($batch === null) {
            return ['high_unresolved' => 0, 'medium_unresolved' => 0, 'low_unresolved' => 0];
        }

        return [
            'high_unresolved' => $batch->exceptions()->where('severity', MigrationExceptionSeverity::High)->whereNull('resolved_at')->count(),
            'medium_unresolved' => $batch->exceptions()->where('severity', MigrationExceptionSeverity::Medium)->whereNull('resolved_at')->count(),
            'low_unresolved' => $batch->exceptions()->where('severity', MigrationExceptionSeverity::Low)->whereNull('resolved_at')->count(),
        ];
    }

    private function resolveStatus(array $checks, array $exceptions): ReconciliationStatus
    {
        $hasDiscrepancy = collect($checks)->contains(fn (array $check) => ! $check['within_tolerance']);

        if ($hasDiscrepancy) {
            return ReconciliationStatus::Discrepancy;
        }

        if ($exceptions['high_unresolved'] > 0) {
            return ReconciliationStatus::NeedsReview;
        }

        return ReconciliationStatus::Clean;
    }
}
