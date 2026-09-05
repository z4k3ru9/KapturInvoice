<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'legacy_vendor_id',
    'name', 'email', 'phone', 'website',
    'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code',
    'notes',
])]
class Vendor extends Model
{
    use BelongsToCompany, SoftDeletes;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
