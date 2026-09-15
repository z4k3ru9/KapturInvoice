<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * "Vendor payments use a parallel immutable event model: proof is
 * required before verification, allocations may be partial, one verified
 * event produces one Vendor Payment Receipt, and later corrections
 * create linked amendments or reversals without mutating the original
 * event." — docs/rebuild/specs/04-billing-and-receivables/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Mirrors PaymentStatus's
 * Pending/Verified/Reversed states — a vendor payment starts Pending via
 * App\Actions\Procurement\RecordVendorPayment and only reaches Verified
 * through App\Actions\Procurement\VerifyVendorPayment.
 */
enum VendorPaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Reversed = 'reversed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Reversed => 'danger',
        };
    }

    /** @return array<int, self> */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Pending => [self::Verified, self::Reversed],
            self::Verified => [self::Reversed],
            self::Reversed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Reversed;
    }
}
