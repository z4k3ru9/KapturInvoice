<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The "Job" aggregate (docs/rebuild/specs/03-sales-and-job/Specs.md) — a
 * NEW aggregate, never an extension of the frozen `Project`/`Task` models
 * (see docs/REFACTOR_PLAN.md §2 risk #3 and Project's own docblock). Only
 * ever created from an Accepted Quotation via
 * App\Actions\Sales\CreateSalesOrderFromQuotation, which also snapshots
 * `source_snapshot` and copies items into `sales_order_items` — the two
 * are never re-synced from the quotation afterward.
 */
#[Fillable([
    'company_id', 'client_id', 'quotation_id', 'number', 'status',
    'approved_value', 'source_snapshot',
])]
class SalesOrder extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'approved_value' => 'decimal:2',
            'source_snapshot' => 'array',
            'approved_at' => 'datetime',
            'operational_closed_at' => 'datetime',
            'financial_closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PaymentMilestone::class)->orderBy('sort_order');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(JobVariation::class)->latest('id');
    }

    /**
     * "Separate operational closure from financial closure" (Specs.md) —
     * the job is fully Closed only once both sides are independently
     * marked, not as a side effect of the `Closed` status transition
     * alone.
     */
    public function isFullyClosed(): bool
    {
        return $this->operational_closed_at !== null && $this->financial_closed_at !== null;
    }
}
