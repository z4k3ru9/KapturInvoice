<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks provenance for a canonical record imported from a legacy system:
 * source system/version/ID, the migration batch, and its exception status.
 * Scaffolded in Phase 01 (docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md
 * §6) but not yet written by `import:invoiceninja-v4`/`-v5` — those
 * commands still use their own `legacy_*_id` columns until Phase 07
 * (docs/rebuild/specs/07-migration-and-cutover) reworks them against the
 * split canonical schema. Uniqueness identity matches
 * docs/rebuild/Specs.md §14: source system + source version + company +
 * entity type + source ID.
 */
#[Fillable([
    'company_id', 'source_system', 'source_version', 'entity_type', 'source_id',
    'canonical_type', 'canonical_id', 'batch', 'status',
])]
class SourceRecord extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
