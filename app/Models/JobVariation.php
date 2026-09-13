<?php

namespace App\Models;

use App\Enums\JobVariationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only record of one approved overrun/out-of-scope/substitution
 * change to a job's billable value (FINALIZED-DECISIONS.md §4). Written
 * only through App\Actions\Sales\ApproveJobVariation — never updated or
 * deleted, so `sales_orders.approved_value`'s history stays fully
 * reconstructible from this table.
 */
#[Fillable(['sales_order_id', 'approved_by_user_id', 'type', 'reason', 'amount', 'value_before', 'value_after', 'approved_at'])]
class JobVariation extends Model
{
    protected function casts(): array
    {
        return [
            'type' => JobVariationType::class,
            'amount' => 'decimal:2',
            'value_before' => 'decimal:2',
            'value_after' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
