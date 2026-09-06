<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'legacy_client_id',
    'name', 'currency_code', 'email', 'phone', 'website',
    'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code',
    'tax_number', 'id_number', 'default_discount', 'default_discount_is_percentage', 'notes',
])]
class Client extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'paid_to_date' => 'decimal:2',
            'default_discount' => 'decimal:2',
            'default_discount_is_percentage' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasMany
    {
        return $this->contacts()->where('is_primary', true);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
