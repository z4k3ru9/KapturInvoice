<?php

namespace App\Models;

use App\Enums\VendorPurchaseOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'vendor_id', 'number', 'status',
    'po_date', 'due_date', 'delivery_date', 'terms', 'notes', 'total',
])]
class VendorPurchaseOrder extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => VendorPurchaseOrderStatus::class,
            'po_date' => 'date',
            'due_date' => 'date',
            'delivery_date' => 'date',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorPurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    public function variances(): HasMany
    {
        return $this->hasMany(VendorPoVariance::class)->latest('id');
    }

    /** The approved payment ceiling: this PO's total plus every approved variance. */
    public function paymentCeiling(): float
    {
        return round((float) $this->total + (float) $this->variances()->sum('amount'), 2);
    }
}
