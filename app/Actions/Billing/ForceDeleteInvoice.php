<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Draft, never-referenced Invoice row — the one
 * narrow exception FINALIZED-DECISIONS.md §2's "issued documents ...
 * never physically deleted" carves out: a Draft that was never issued,
 * never paid, and never corrected was never really "issued" in the first
 * place, so there is no history to protect.
 *
 * Ported from the equivalent static guard in the pre-TallStackUI Filament
 * admin (dropped, unreplaced, when Filament was removed) — same
 * predicate, moved into the Action
 * layer per this app's "financial logic belongs in tested domain actions,
 * not UI" rule, since that placement was itself a minor architecture
 * violation carried over from the legacy codebase.
 *
 * `App\Providers\AppServiceProvider::registerCompanyRoleGate()` already
 * restricts the `forceDelete` ability to the company Owner for every
 * `BelongsToCompany` model — this action reuses that central check via
 * `$actor->can('forceDelete', $invoice)` rather than re-implementing a
 * role check here.
 */
class ForceDeleteInvoice
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Safe iff: Draft status, and never paid, allocated against, or
     * corrected (amended/void-and-reissued).
     */
    public static function isSafeToForceDelete(Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft
            && ! $invoice->payments()->exists()
            && ! $invoice->allocations()->exists()
            && ! $invoice->correction()->exists();
    }

    public function forceDelete(Invoice $invoice, User $actor): void
    {
        if (! $actor->can('forceDelete', $invoice)) {
            throw new RuntimeException('Only the company Owner may permanently delete an invoice.');
        }

        if (! self::isSafeToForceDelete($invoice)) {
            throw new RuntimeException('Only a Draft invoice with no payments, allocations, or amendment/void-reissue history can be permanently deleted.');
        }

        DB::transaction(function () use ($invoice) {
            $this->auditLogger->record(
                $invoice->company,
                'invoice.force_deleted',
                $invoice,
                $invoice->getAttributes(),
                [],
            );

            $invoice->forceDelete();
        });
    }
}
