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
            // Was 'primary' (the tenant's own brand color, reserved for
            // the page's single main commit action per
            // AppServiceProvider::registerActionColorPalette()) — that
            // made an "Issued" badge's meaning vary by tenant brand
            // rather than by anything about the status itself, and
            // collided with the reserved role. 'info' matches Approved/
            // Sent/Viewed: still moving through the lifecycle, not yet
            // resolved to Paid/Overdue/Void.
            self::Issued => 'info',
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

    /**
     * True once an invoice's header/totals/tax snapshot must be treated as
     * immutable — the only two statuses this excludes are `Draft` and
     * `Approved`, the sole pre-issuance states where App\Actions\Billing\
     * IssueInvoice hasn't yet frozen a tax snapshot
     * (App\Models\InvoiceTaxSnapshot). Every other case is locked,
     * including `Cancelled` (legacy-only — memory.md's "InvoiceStatus dual
     * case set" decision: it only appears on imported/pre-migration rows,
     * which are read-only history and must never be edited via this form
     * either). CORRECTION: `Sent`/`Viewed` do occur on app-created
     * documents too — confirmed during the status-transition automation
     * review — `Sent` is set automatically when BillingMailer sends the
     * invoice email, and `Viewed` when the client first opens the portal
     * link (App\Livewire\Portal\ViewInvoice); this class's own
     * `allowedNextStates()` simply never routes an Issued invoice through
     * either of them (`Draft -> Approved -> Issued` bypasses Sent/Viewed
     * entirely for a plain app-created invoice), so they're locked here
     * for the same reason as any other post-issuance state, not because
     * they're unreachable. `Partial`/`Paid`/`Overdue` double as the
     * post-issuance payment states an Issued invoice reaches
     * (allowedNextStates() above) — exactly the same status set
     * App\Actions\Billing\AmendIssuedInvoice/VoidAndReissueInvoice accept
     * via canTransitionTo(Amended)/canTransitionTo(Void). Consulted by
     * App\Livewire\TallStackInvoiceForm::save() to refuse a direct header
     * edit once this is true — the only legal correction path from here on
     * is Amend or Void & reissue.
     */
    public function isLocked(): bool
    {
        return ! in_array($this, [self::Draft, self::Approved], true);
    }
}
