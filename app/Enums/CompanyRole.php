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
