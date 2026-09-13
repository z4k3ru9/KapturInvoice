<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only privileged-action log. Write only through
 * App\Services\AuditLogger — never edit or delete a row, including as
 * Owner (docs/rebuild/specs/FINALIZED-DECISIONS.md §2). There is no
 * `updated_at`: once created, a row cannot change.
 */
#[Fillable(['company_id', 'user_id', 'action', 'entity_type', 'entity_id', 'reason', 'before', 'after'])]
class AuditEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->created_at ??= now();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** The user who performed the action, if any (system-initiated events may have none). */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
