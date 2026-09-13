<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Don't send tier N's reminder for this invoice on its next matching
 * date." Written only by App\Actions\Billing\SuppressReminder; append-only
 * — a later suppression for the same invoice+tier is a new row, never an
 * edit of a prior one. See docs/rebuild/specs/FINALIZED-DECISIONS.md §5
 * and docs/rebuild/specs/06-documents-portal-reporting/Specs.md.
 */
#[Fillable(['company_id', 'invoice_id', 'tier', 'suppressed_by_user_id', 'reason', 'suppressed_at'])]
class ReminderSuppression extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'suppressed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function suppressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suppressed_by_user_id');
    }
}
