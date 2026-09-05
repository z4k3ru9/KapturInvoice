<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The Filament tenant model: one row per billed entity. KapturInvoice runs
 * two of these, each with its own public-homepage domain and invoice
 * numbering sequence, sharing a single admin panel.
 */
#[Fillable([
    'name', 'slug', 'domain', 'email', 'phone',
    'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code',
    'currency_code', 'timezone', 'logo_path', 'primary_color', 'secondary_color',
    'invoice_prefix', 'invoice_next_number',
    'quote_prefix', 'quote_next_number',
    'credit_prefix', 'credit_next_number',
])]
class Company extends Model implements HasName
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'invoice_next_number' => 'integer',
            'quote_next_number' => 'integer',
            'credit_next_number' => 'integer',
        ];
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
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

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }
}
