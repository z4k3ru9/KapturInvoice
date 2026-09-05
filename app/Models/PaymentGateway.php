<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's configured payment gateway. `config` (API credentials) is
 * encrypted at rest — see docs/invoiceninja-v4-schema-reference.md §2.4,
 * which flags the legacy `account_gateways.config` plaintext column as a
 * discipline to explicitly not carry over.
 */
#[Fillable([
    'company_id', 'legacy_account_gateway_id',
    'name', 'driver', 'is_enabled', 'config', 'accepted_credit_cards',
    'show_address', 'require_cvv',
    'fee_amount', 'fee_percent', 'fee_tax_name', 'fee_tax_rate',
])]
class PaymentGateway extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'encrypted',
            'accepted_credit_cards' => 'array',
            'show_address' => 'boolean',
            'require_cvv' => 'boolean',
            'fee_amount' => 'decimal:2',
            'fee_percent' => 'decimal:3',
            'fee_tax_rate' => 'decimal:3',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
