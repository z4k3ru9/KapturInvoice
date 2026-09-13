<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The job state matrix from docs/rebuild/specs/03-sales-and-job/Specs.md.
 * Progresses forward one step at a time; Cancelled is reachable from any
 * state before Closed (per "Separate operational closure from financial
 * closure" — Closed is the terminal state reached only once both
 * `operational_closed_at` and `financial_closed_at` are set, not a status
 * transition of its own — see App\Models\SalesOrder).
 */
enum SalesOrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Procurement = 'procurement';
    case InProgress = 'in_progress';
    case Delivered = 'delivered';
    case HandedOver = 'handed_over';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Procurement => 'Procurement',
            self::InProgress => 'In Progress',
            self::Delivered => 'Delivered',
            self::HandedOver => 'Handed Over',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved, self::Procurement, self::InProgress => 'info',
            self::Delivered, self::HandedOver => 'warning',
            self::Closed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** The single forward step from this state, per the approved matrix. */
    private function nextForwardState(): ?self
    {
        return match ($this) {
            self::Draft => self::Approved,
            self::Approved => self::Procurement,
            self::Procurement => self::InProgress,
            self::InProgress => self::Delivered,
            self::Delivered => self::HandedOver,
            self::HandedOver => self::Closed,
            self::Closed, self::Cancelled => null,
        };
    }

    /** Every state this one may legally move to. */
    public function allowedNextStates(): array
    {
        if ($this->isTerminal()) {
            return [];
        }

        $states = [];

        if ($forward = $this->nextForwardState()) {
            $states[] = $forward;
        }

        // Cancellable from any non-terminal, pre-Closed state.
        if ($this !== self::Closed) {
            $states[] = self::Cancelled;
        }

        return $states;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed || $this === self::Cancelled;
    }
}
