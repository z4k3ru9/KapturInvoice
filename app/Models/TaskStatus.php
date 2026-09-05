<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'legacy_task_status_id', 'name', 'sort_order'])]
class TaskStatus extends Model
{
    use BelongsToCompany;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
