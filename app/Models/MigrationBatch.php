<?php

namespace App\Models;

use App\Enums\MigrationBatchStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per run of `import:invoiceninja-v4`/`import:invoiceninja-v5` —
 * see docs/rebuild/specs/07-migration-and-cutover/Specs.md. Written only
 * by those two commands, never edited from the UI.
 */
#[Fillable([
    'company_id', 'source_system', 'connection', 'status',
    'last_completed_step', 'error_message', 'stats',
    'started_at', 'completed_at', 'failed_at',
])]
class MigrationBatch extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => MigrationBatchStatus::class,
            'stats' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(MigrationException::class);
    }

    public function reconciliationRuns(): HasMany
    {
        return $this->hasMany(ReconciliationRun::class);
    }
}
