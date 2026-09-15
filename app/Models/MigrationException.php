<?php

namespace App\Models;

use App\Enums\MigrationExceptionSeverity;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quarantined source row from a legacy import — "Quarantine uncertain
 * mappings and missing financial data" (Phase 07 Specs.md). The source
 * row is still imported (never invented or silently skipped); this just
 * flags it as excluded from confirmed reconciliation until an Owner
 * reviews and resolves it. Unique per (company, entity_type, source_id)
 * so re-running the same import batch never duplicates the same
 * exception.
 */
#[Fillable(['migration_batch_id', 'company_id', 'entity_type', 'source_id', 'severity', 'reason'])]
class MigrationException extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'severity' => MigrationExceptionSeverity::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function migrationBatch(): BelongsTo
    {
        return $this->belongsTo(MigrationBatch::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
