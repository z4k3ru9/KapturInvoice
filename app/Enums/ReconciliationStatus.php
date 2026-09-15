<?php

namespace App\Enums;

/**
 * The outcome of one `App\Services\Migration\ReconciliationService` run —
 * docs/rebuild/specs/07-migration-and-cutover/Specs.md's "Reconcile
 * counts, line totals, discounts, tax, payments, allocations, open
 * balances, vendor due balances, source numbers/dates, and exceptions per
 * company." `Clean` requires every check within tolerance AND zero
 * unresolved High-severity `MigrationException` rows — a batch can pass
 * every numeric check and still be `NeedsReview` if a High exception is
 * outstanding, since Specs.md's cutover sequence requires "resolve
 * high-severity exceptions" as its own gate before business sign-off.
 */
enum ReconciliationStatus: string
{
    case Clean = 'clean';
    case NeedsReview = 'needs_review';
    case Discrepancy = 'discrepancy';

    public function getLabel(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::NeedsReview => 'Needs review',
            self::Discrepancy => 'Discrepancy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Clean => 'success',
            self::NeedsReview => 'warning',
            self::Discrepancy => 'danger',
        };
    }
}
