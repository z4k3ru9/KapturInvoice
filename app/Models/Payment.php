<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'client_id', 'invoice_id', 'contact_id', 'legacy_payment_id',
    'sales_order_id', 'amount', 'refunded_amount', 'currency_code', 'exchange_rate',
    'method', 'gateway', 'gateway_reference', 'status',
    'last4', 'payment_date', 'notes', 'proof_path', 'reference',
    'cheque_cleared_at', 'verified_at', 'verified_by_user_id',
])]
class Payment extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'payment_date' => 'date',
            'cheque_cleared_at' => 'datetime',
            'verified_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** The Phase 03 Job this payment is associated with, if any. */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /** Active + superseded rows both stay attached — see PaymentAllocation::$isActive. */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function verificationEvents(): HasMany
    {
        return $this->hasMany(PaymentVerificationEvent::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class);
    }
}
