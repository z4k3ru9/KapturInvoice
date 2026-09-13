<?php

namespace App\Models;

use App\Enums\VendorPaymentStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A parallel immutable event to the customer-side Payment (Phase 04) —
 * see docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Starts
 * VendorPaymentStatus::Pending via
 * App\Actions\Procurement\RecordVendorPayment; only a Verified event
 * (App\Actions\Procurement\VerifyVendorPayment) counts toward
 * App\Services\Procurement\RecalculateVendorBillPayments and may be
 * issued a VendorPaymentReceipt. `number` is legacy from before this
 * event model existed and is no longer populated — the launch-code 'VPR'
 * number now belongs to the receipt, issued at verification, not to this
 * row at record time.
 */
#[Fillable([
    'company_id', 'vendor_bill_id', 'number', 'status',
    'amount', 'payment_date', 'method', 'reference', 'proof_path', 'notes',
    'cheque_cleared_at', 'verified_at', 'verified_by_user_id',
])]
class VendorPayment extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => VendorPaymentStatus::class,
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'cheque_cleared_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(VendorPaymentReceipt::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(VendorPaymentReversal::class);
    }
}
