<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * "It follows the Delivery Order pattern: multiple per job, Draft ->
 * Submitted -> Approved with Cancelled from any non-approved state, Staff
 * and higher may submit/approve, approval snapshots the report, and
 * approved reports are immutable and never physically deleted." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §10. Mirrors
 * App\Enums\VendorBillStatus's `allowedNextStates()`/`canTransitionTo()`
 * shape.
 */
enum ServiceReportStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<int, self> */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Cancelled],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Cancelled], true);
    }
}
