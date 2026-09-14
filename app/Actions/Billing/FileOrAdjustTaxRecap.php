<?php

namespace App\Actions\Billing;

use App\Enums\CompanyRole;
use App\Models\TaxRecap;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Recaps contain configurable fields for reporting period, external
 * tax-system reference/serial, manual-entry status, filing/entry date,
 * notes, and attachment/reference; adjustments require reason and audit
 * data and never mutate the invoice snapshot." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §33,
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md. A Codex review
 * finding on PR #4: Phase 06B Slice 1 added these columns and a read-only
 * Infolist/PDF for them, but no action anywhere actually wrote to them —
 * an accountant had no way to record that a recap was filed with the tax
 * authority, or to correct one afterward with a reason.
 *
 * "Accountant | ... tax recap, reconciliation" — Specs.md §10, the same
 * tier as invoice issuance (App\Enums\CompanyRole::invoiceIssuanceRoles()).
 * Never touches the invoice's own tax snapshot/total — only this recap's
 * own filing metadata. The first time this runs for a recap it is a plain
 * filing (no reason required); every subsequent call is an adjustment to
 * an already-filed recap and requires one, recorded on the recap itself
 * (`adjusted_at`/`adjusted_by_user_id`/`adjustment_reason`) and in the
 * audit log — append-only in spirit (each call overwrites the filing
 * fields but the reason for every correction is preserved via the audit
 * event's own history, mirroring how VendorPoVariance layers on top of an
 * immutable PO rather than a full separate amendment table).
 */
class FileOrAdjustTaxRecap
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{external_reference?: ?string, manual_entry_status?: ?string, filing_date?: mixed, notes?: ?string, attachment_reference?: ?string}  $data
     */
    public function file(TaxRecap $recap, array $data, User $actor, ?string $adjustmentReason = null): TaxRecap
    {
        if (! $actor->hasCompanyRole($recap->invoice->company, ...CompanyRole::invoiceIssuanceRoles())) {
            throw new RuntimeException('Only Accountant, Admin, or Owner may file or adjust a tax recap.');
        }

        $alreadyFiled = filled($recap->filing_date) || $recap->manual_entry_status === 'filed';

        $before = [
            'external_reference' => $recap->external_reference,
            'manual_entry_status' => $recap->manual_entry_status,
            'filing_date' => optional($recap->filing_date)->toDateString(),
            'notes' => $recap->notes,
            'attachment_reference' => $recap->attachment_reference,
        ];

        $attributes = [
            'external_reference' => $data['external_reference'] ?? $recap->external_reference,
            'manual_entry_status' => $data['manual_entry_status'] ?? $recap->manual_entry_status,
            'filing_date' => $data['filing_date'] ?? $recap->filing_date,
            'notes' => $data['notes'] ?? $recap->notes,
            'attachment_reference' => $data['attachment_reference'] ?? $recap->attachment_reference,
        ];

        if ($alreadyFiled) {
            if (blank($adjustmentReason)) {
                throw new RuntimeException('A reason is required to adjust an already-filed tax recap.');
            }

            $attributes['adjusted_at'] = now();
            $attributes['adjusted_by_user_id'] = $actor->id;
            $attributes['adjustment_reason'] = $adjustmentReason;
        }

        $recap->forceFill($attributes)->save();

        $this->auditLogger->record(
            $recap->invoice->company,
            $alreadyFiled ? 'tax_recap.adjusted' : 'tax_recap.filed',
            $recap,
            $before,
            [
                'external_reference' => $recap->external_reference,
                'manual_entry_status' => $recap->manual_entry_status,
                'filing_date' => optional($recap->filing_date)->toDateString(),
                'notes' => $recap->notes,
                'attachment_reference' => $recap->attachment_reference,
            ],
            $alreadyFiled ? $adjustmentReason : null,
        );

        return $recap->fresh();
    }
}
