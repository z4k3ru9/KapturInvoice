<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted snapshot of `migration:reconcile`'s output — "Reconcile
 * counts, line totals, discounts, tax, payments, allocations, open
 * balances, vendor due balances, source numbers/dates, and exceptions
 * per company" (Phase 07 Specs.md). `metrics` holds the full structured
 * comparison; `status` is `clean` only when every check in `metrics` is
 * within tolerance and no unresolved High-severity exception exists.
 */
#[Fillable(['company_id', 'migration_batch_id', 'status', 'metrics', 'run_at'])]
class ReconciliationRun extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'metrics' => 'array',
            'run_at' => 'datetime',
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
}
