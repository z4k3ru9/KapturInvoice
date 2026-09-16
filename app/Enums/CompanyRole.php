<?php

namespace App\Enums;

/**
 * Internal membership roles from docs/rebuild/PRD.md §"Roles" /
 * docs/rebuild/Specs.md §10. Vendor ("entity record only, no login") and
 * Client (scoped read-only portal) are not internal accounts and so are
 * not represented here — they never hold a `company_user` row.
 */
enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Sales = 'sales';
    case Staff = 'staff';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Accountant => 'Accountant',
            self::Sales => 'Sales',
            self::Staff => 'Staff',
            self::Auditor => 'Auditor',
        };
    }

    /**
     * A single-sentence, plain-language statement of this role's real
     * permission boundary — the single source of truth for the "Users &
     * Roles" UI (Stitch prompt 11's edit-role modal: "every role's real
     * permission boundary stated in plain language right where it's
     * assigned"). Grounded in this file's own docblocks (which methods a
     * role appears in above) plus docs/rebuild/PRD.md §"Roles" and
     * docs/rebuild/Specs.md §10 "Protected actions" — never hand-copy this
     * prose elsewhere; call this method instead so the UI can't drift out
     * of sync with the actual role-array membership above.
     */
    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including force-delete and financial-close overrides — only role that can override an outstanding-balance job closure.',
            self::Admin => 'Operates approved workflows and exceptions — manages users and settings, approves job/vendor variances and issued-document amendments — but never force-deletes issued records or overrides an outstanding-balance job closure (Owner only).',
            self::Accountant => 'Issues invoices, verifies customer payments, approves vendor bills, and handles reconciliation — cannot manage users/settings or amend an already-issued document.',
            self::Sales => 'Owns commercial work — creates and manages quotations, sales orders, and jobs — cannot verify payments, issue invoices, or manage users/settings.',
            self::Staff => 'Handles delivery and handover — records deliveries and handover reports — cannot verify payments, issue invoices, or manage users/settings.',
            self::Auditor => 'Read-only across every module — cannot create, edit, or approve anything, ever.',
        };
    }

    /**
     * Every role except Auditor may create/update/delete company-scoped
     * records (subject to further per-action policy once those actions
     * exist — see docs/rebuild/Specs.md §10 "Protected actions"). Auditor
     * is read-only everywhere per the PRD.
     *
     * @return array<int, self>
     */
    public static function mutatingRoles(): array
    {
        return [self::Owner, self::Admin, self::Accountant, self::Sales, self::Staff];
    }

    /** Owner/Admin only, per "View settings" in the Specs.md §10 table. */
    public static function settingsRoles(): array
    {
        return [self::Owner, self::Admin];
    }

    /** Owner/Accountant only, per the period-reopen rule in Specs.md §01/FINALIZED-DECISIONS.md §2. */
    public static function periodReopenRoles(): array
    {
        return [self::Owner, self::Accountant];
    }

    /**
     * Owner/Admin only, per docs/rebuild/specs/03-sales-and-job/Specs.md
     * ("Support overrun, out-of-scope work, and item substitutions only
     * through Owner/Admin approval with reason") and
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §4.
     *
     * @return array<int, self>
     */
    public static function jobVariationApprovalRoles(): array
    {
        return [self::Owner, self::Admin];
    }

    /**
     * "Accountant and higher verify payments" —
     * docs/rebuild/specs/04-billing-and-receivables/Specs.md.
     *
     * @return array<int, self>
     */
    public static function paymentVerificationRoles(): array
    {
        return [self::Owner, self::Admin, self::Accountant];
    }

    /**
     * "Issue invoice: Accountant and higher after approval requirements." —
     * docs/rebuild/Specs.md §10 (the same "Accountant and higher" tier as
     * {@see paymentVerificationRoles()}). Enforced inside
     * App\Actions\Billing\IssueInvoice itself, not only at the Filament
     * table-action layer, so a direct call can't bypass it either.
     *
     * @return array<int, self>
     */
    public static function invoiceIssuanceRoles(): array
    {
        return [self::Owner, self::Admin, self::Accountant];
    }

    /**
     * "Amend issued document: Admin/Owner for document edits; preserve
     * original." — docs/rebuild/Specs.md §10. A strict subset of
     * {@see invoiceIssuanceRoles()}, so an actor authorized to amend or
     * void-and-reissue also satisfies the issuance check that
     * App\Actions\Billing\IssueInvoice runs internally when it issues the
     * replacement document.
     *
     * @return array<int, self>
     */
    public static function documentAmendmentRoles(): array
    {
        return [self::Owner, self::Admin];
    }

    /**
     * "Accountant and higher may self-approve" a vendor bill —
     * docs/rebuild/specs/05-procurement-and-delivery/Specs.md.
     *
     * @return array<int, self>
     */
    public static function vendorBillApprovalRoles(): array
    {
        return [self::Owner, self::Admin, self::Accountant];
    }

    /**
     * Owner/Admin only — mirrors `jobVariationApprovalRoles()` for the
     * equivalent vendor-side exception (FINALIZED-DECISIONS.md §4).
     *
     * @return array<int, self>
     */
    public static function vendorPoVarianceApprovalRoles(): array
    {
        return [self::Owner, self::Admin];
    }

    /**
     * Owner/Admin only. The override lever for the status-transition
     * automation (Invoice auto-Overdue, recurring auto-generate/issue/
     * send, SalesOrder auto-close-operationally): pausing automation on a
     * specific record for moderation or a revision that needs approval.
     * Same tier as {@see vendorPoVarianceApprovalRoles()} — an exceptional
     * override, not a routine action.
     *
     * @return array<int, self>
     */
    public static function holdApprovalRoles(): array
    {
        return [self::Owner, self::Admin];
    }

    /**
     * "Staff and higher can record/approve delivery and handover" —
     * every role except the read-only Auditor, i.e. the same set as
     * {@see mutatingRoles()}.
     *
     * @return array<int, self>
     */
    public static function deliveryAndHandoverRoles(): array
    {
        return self::mutatingRoles();
    }
}
