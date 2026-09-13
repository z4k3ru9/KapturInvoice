<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Assigns the next document number for a company and atomically advances
 * its counter, in the `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` format required
 * by docs/rebuild/specs/FINALIZED-DECISIONS.md §2 (e.g.
 * `KJA-INV-2026090001`) — backed by the `numbering_sequences` table (one
 * row per company/document-type/year), not the legacy
 * `companies.invoice_next_number`-style columns. Those legacy columns are
 * left untouched: they only ever back already-issued historical numbers,
 * which must remain exactly as issued (see
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §6) — every number allocated
 * from here on comes from this generator instead.
 *
 * The sequence increments across months and resets only at the start of
 * the next year (never monthly), is scoped independently per company and
 * document type, and is allocated inside a DB transaction with
 * `lockForUpdate()` so two concurrent creates can't race the same counter
 * value (on SQLite, used in dev/test, `lockForUpdate()` is a no-op — SQLite
 * serializes writers at the file level regardless; MySQL/Postgres get a
 * real row lock).
 *
 * A company's `code` (and, implicitly, its document-type codes) locks the
 * moment any number is first issued for it — see
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §1: "Codes and document-type
 * codes are configurable before first issuance, then locked for
 * continuity."
 */
class DocumentNumberGenerator
{
    /** Maps the caller's sequence key to the launch document-type code from FINALIZED-DECISIONS.md §2. */
    private const DOCUMENT_TYPES = [
        'invoice' => 'INV',
        'quote' => 'QUO',
        'credit' => 'CR',
        // Phase 03 (docs/rebuild/specs/03-sales-and-job): a system-generated
        // Customer Order Confirmation, issued only when a quotation is
        // accepted without a supplied customer PO (FINALIZED-DECISIONS.md
        // §2/§4) — and the Sales Order / Job aggregate itself.
        'coc' => 'COC',
        'sales_order' => 'SO',
        // Phase 04 (docs/rebuild/specs/04-billing-and-receivables): a
        // numbered receipt for one verified payment event, and an
        // amendment invoice's own annual sequence — FINALIZED-DECISIONS.md
        // §2: "Amendments use the original code plus -A and their own
        // annual sequence, for example KA-INV-A-2026090001."
        'receipt' => 'RCT',
        'invoice_amendment' => 'INV-A',
    ];

    public function next(Company $company, string $sequence): string
    {
        if (! array_key_exists($sequence, self::DOCUMENT_TYPES)) {
            throw new InvalidArgumentException("Unknown numbering sequence [{$sequence}].");
        }

        if (blank($company->code)) {
            throw new InvalidArgumentException(
                "Company [{$company->id}] has no numbering code configured — set Company::code before issuing documents."
            );
        }

        $documentType = self::DOCUMENT_TYPES[$sequence];
        $now = now();
        $year = $now->year;

        return DB::transaction(function () use ($company, $documentType, $year, $now) {
            $row = NumberingSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = NumberingSequence::create([
                    'company_id' => $company->id,
                    'document_type' => $documentType,
                    'year' => $year,
                    'next_sequence' => 1,
                ]);

                // Re-fetch under the lock: creating doesn't itself take the
                // row lock, and a concurrent allocator could otherwise read
                // the same freshly-created row before either has locked it.
                $row = NumberingSequence::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $sequenceNumber = $row->next_sequence;

            $row->increment('next_sequence');
            $row->increment('lock_version');

            if (blank($company->codes_locked_at)) {
                $company->forceFill(['codes_locked_at' => $now])->save();
            }

            $padded = str_pad((string) $sequenceNumber, 4, '0', STR_PAD_LEFT);

            return "{$company->code}-{$documentType}-{$now->format('Ym')}{$padded}";
        });
    }
}
