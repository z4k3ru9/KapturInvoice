<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A Vendor PO's own lifecycle — deliberately simple (Specs.md only ever
 * refers to "the approved PO amount" as the payment ceiling, not a richer
 * state matrix). The PO's `total` is immutable once created; exceeding it
 * is handled by `App\Models\VendorPoVariance`, never by re-editing this.
 */
enum VendorPurchaseOrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<int, self> */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Cancelled],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return $this !== self::Draft;
    }
}
