<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    'default_payment_terms', 'default_tax_rate_1_id', 'default_tax_rate_2_id',
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

    public function defaultTaxRate1(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_tax_rate_1_id');
    }

    public function defaultTaxRate2(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_tax_rate_2_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(PaymentGateway::class);
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

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function taskStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function proposalTemplates(): HasMany
    {
        return $this->hasMany(ProposalTemplate::class);
    }

    public function proposalSnippets(): HasMany
    {
        return $this->hasMany(ProposalSnippet::class);
    }
}
