<?php

namespace App\Enums;

/**
 * The full state list from docs/rebuild/specs/03-sales-and-job/Specs.md.
 * Order of the case list mirrors the intended lifecycle: a quotation is
 * drafted, internally Approved to be sent, Sent to the customer, and only
 * then reaches one of the four customer-response terminal states
 * (Accepted/Rejected/Expired) or is Cancelled from any non-terminal state.
 * See canTransitionTo() for the enforced edges.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved, self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected, self::Cancelled => 'danger',
            self::Expired => 'warning',
        };
    }

    /** Every state this one may legally move to. */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Cancelled],
            self::Approved => [self::Sent, self::Cancelled],
            self::Sent => [self::Accepted, self::Rejected, self::Expired, self::Cancelled],
            self::Accepted, self::Rejected, self::Expired, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNextStates() === [];
    }
}
