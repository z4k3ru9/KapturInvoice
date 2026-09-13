<?php

namespace App\Models;

use App\Enums\PricingMode;
use App\Enums\QuotationStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The canonical Phase 03 quotation aggregate
 * (docs/rebuild/specs/03-sales-and-job/Specs.md) — see the migration's
 * docblock for why this is a new table rather than the legacy
 * `invoices`/`type=quote` rows. A quotation may only be created with
 * status `Draft`; every later transition is enforced through
 * `QuotationStatus::canTransitionTo()` (see App\Actions\Sales), never a
 * bare `update(['status' => ...])` call.
 */
#[Fillable([
    'company_id', 'client_id', 'number', 'status', 'pricing_mode',
    'discount', 'discount_is_percentage', 'quotation_date', 'valid_until',
    'customer_po_number', 'customer_po_date', 'customer_po_is_system_generated',
    'terms', 'notes',
])]
class Quotation extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'pricing_mode' => PricingMode::class,
            'discount' => 'decimal:2',
            'discount_is_percentage' => 'boolean',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'customer_po_date' => 'date',
            'customer_po_is_system_generated' => 'boolean',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** The job created once this quotation is accepted, if any. */
    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }
}
