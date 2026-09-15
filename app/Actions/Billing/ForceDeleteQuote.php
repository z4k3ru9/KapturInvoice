<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Draft, never-converted legacy Quote row (an
 * `Invoice` with `type = InvoiceType::Quote` —
 * `App\Filament\Resources\Quotes`'s legacy/imported register, distinct
 * from the canonical `App\Models\Quotation`). See
 * App\Actions\Billing\ForceDeleteInvoice's docblock for the shared
 * rationale (this is the same narrow "never really issued" exception).
 *
 * Ported from `App\Filament\Resources\Quotes\Tables\
 * QuotesTable::isSafeToForceDelete()` (dropped, unreplaced, when Filament
 * was removed).
 */
class ForceDeleteQuote
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Safe iff: everything ForceDeleteInvoice::isSafeToForceDelete()
     * requires on this same record, and it was never converted into a
     * real invoice.
     */
    public static function isSafeToForceDelete(Invoice $quote): bool
    {
        return ForceDeleteInvoice::isSafeToForceDelete($quote)
            && ! $quote->convertedInvoices()->exists();
    }

    public function forceDelete(Invoice $quote, User $actor): void
    {
        if (! $actor->can('forceDelete', $quote)) {
            throw new RuntimeException('Only the company Owner may permanently delete a quote.');
        }

        if (! self::isSafeToForceDelete($quote)) {
            throw new RuntimeException('Only a Draft quote with no payments, allocations, corrections, or converted invoices can be permanently deleted.');
        }

        DB::transaction(function () use ($quote) {
            $this->auditLogger->record(
                $quote->company,
                'quote.force_deleted',
                $quote,
                $quote->getAttributes(),
                [],
            );

            $quote->forceDelete();
        });
    }
}
