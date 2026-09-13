<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * "Vendor bills flow through Draft, Submitted, Approved, Partially Paid,
 * and Paid; Accountant and higher may self-approve with audit evidence." —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md.
 * PartiallyPaid/Paid are reached only by
 * App\Services\Procurement\RecalculateVendorBillPayments as payments are
 * recorded, never by a direct transition.
 */
enum VendorBillStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Approved => 'warning',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<int, self> */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Cancelled],
            self::Approved => [self::PartiallyPaid, self::Paid],
            self::PartiallyPaid => [self::Paid],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }
}
