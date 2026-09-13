<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (company, document type, year); the source of truth for the
 * new `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` numbering format. Only
 * App\Services\DocumentNumberGenerator writes `next_sequence` — always
 * inside a transaction with `lockForUpdate()` so concurrent issuance can't
 * allocate the same number twice. See
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §2.
 */
#[Fillable(['company_id', 'document_type', 'year', 'next_sequence', 'lock_version'])]
class NumberingSequence extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
