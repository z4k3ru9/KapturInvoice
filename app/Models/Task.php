<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'project_id', 'client_id', 'invoice_id', 'task_status_id', 'legacy_task_id',
    'description', 'started_at', 'stopped_at', 'is_running', 'sort_order',
])]
class Task extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'is_running' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    /** Elapsed duration for the recorded segment, in seconds. */
    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        return ($this->stopped_at ?? now())->diffInSeconds($this->started_at);
    }
}
