<?php

namespace App\Models;

use App\Enums\VendorBillStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'vendor_id', 'vendor_purchase_order_id', 'number', 'status',
    'bill_date', 'due_date', 'notes', 'total',
    'document_language',
])]
class VendorBill extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => VendorBillStatus::class,
            'bill_date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'approved_at' => 'datetime',
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

    public function vendorPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(VendorPurchaseOrder::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    /**
     * Printed-document language: this bill's own override when set,
     * otherwise the owning company's `default_document_language`, otherwise
     * Bahasa Indonesia — mirrors App\Models\Invoice::resolveDocumentLanguage().
     * See docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
     * document coverage."
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
