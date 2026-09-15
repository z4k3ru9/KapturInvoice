<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    // Phase 04 (docs/rebuild/specs/04-billing-and-receivables): the new
    // canonical billing lifecycle — "Invoice states: Draft, Approved,
    // Issued, Partially Paid, Paid, with controlled overdue/void/amended
    // states." `Partial` above already covers "Partially Paid"; these four
    // are new. Adding cases to an existing string-backed enum is safe for
    // already-imported legacy rows (their values are untouched) — see
    // docs/REFACTOR_PLAN.md §2 risk #2's note on this same enum.
    case Approved = 'approved';
    case Issued = 'issued';
    case Void = 'void';
    case Amended = 'amended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::Partial => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
            self::Approved => 'Approved',
            self::Issued => 'Issued',
            self::Void => 'Void',
            self::Amended => 'Amended',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent, self::Viewed => 'info',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Cancelled => 'gray',
            self::Approved => 'info',
            self::Issued => 'primary',
            self::Void => 'danger',
            self::Amended => 'gray',
        };
    }

    /**
     * Legal forward edges for the new billing lifecycle
     * (App\Actions\Billing\*), driving Draft -> Approved -> Issued and the
     * billable states an issued invoice can reach as payments land. This is
     * deliberately not enforced retroactively on already-imported legacy
     * invoices/quotes-as-invoice rows — only the new Actions consult it.
     *
     * @return array<int, self>
     */
    public function allowedNextStates(): array
    {
        return match ($this) {
            self::Draft => [self::Approved, self::Cancelled],
            self::Approved => [self::Issued, self::Cancelled],
            self::Issued => [self::Partial, self::Paid, self::Overdue, self::Void, self::Amended],
            self::Partial => [self::Paid, self::Overdue, self::Void, self::Amended],
            self::Overdue => [self::Partial, self::Paid, self::Void, self::Amended],
            // A correction (void-and-reissue or amendment) remains legal
            // even once fully paid — FINALIZED-DECISIONS.md §2's amendment
            // path is not blocked by payment state.
            self::Paid => [self::Void, self::Amended],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStates(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Void, self::Amended], true);
    }
}
