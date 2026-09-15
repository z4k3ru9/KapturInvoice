<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Draft Quotation that no Job (SalesOrder) was ever
 * created from — see App\Actions\Billing\ForceDeleteInvoice's docblock
 * for the shared rationale.
 *
 * Ported from the equivalent table-level guard in the pre-TallStackUI
 * Filament admin (dropped, unreplaced, when Filament was removed).
 */
class ForceDeleteQuotation
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /** Safe iff: Draft status, and no Job was ever created from it. */
    public static function isSafeToForceDelete(Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft
            && ! $quotation->salesOrder()->exists();
    }

    public function forceDelete(Quotation $quotation, User $actor): void
    {
        if (! $actor->can('forceDelete', $quotation)) {
            throw new RuntimeException('Only the company Owner may permanently delete a quotation.');
        }

        if (! self::isSafeToForceDelete($quotation)) {
            throw new RuntimeException('Only a Draft quotation with no job created from it can be permanently deleted.');
        }

        DB::transaction(function () use ($quotation) {
            $this->auditLogger->record(
                $quotation->company,
                'quotation.force_deleted',
                $quotation,
                $quotation->getAttributes(),
                [],
            );

            $quotation->forceDelete();
        });
    }
}
