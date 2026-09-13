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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Filament tenant model: one row per billed entity. KapturInvoice runs
 * two of these, each with its own public-homepage domain and invoice
 * numbering sequence, sharing a single admin panel.
 */
#[Fillable([
    'name', 'slug', 'code', 'is_active', 'domain', 'email', 'phone', 'tax_number',
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

    /**
     * Mirrors the DB defaults for columns callers commonly leave unset
     * (e.g. `Company::create([...])` in tests/seeders) — without this,
     * the in-memory model right after create() would read these as null
     * until a fresh `find()`/`fresh()`, which broke
     * canAccessTenant()'s `$tenant->is_active` check on a
     * just-created company.
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'invoice_next_number' => 'integer',
            'quote_next_number' => 'integer',
            'credit_next_number' => 'integer',
            'is_active' => 'boolean',
            'codes_locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $company) {
            // A numbering code is required to issue any document (see
            // App\Services\DocumentNumberGenerator) and is configurable
            // only before first issuance — but requiring every caller to
            // supply one explicitly at creation would be a footgun for
            // every future company/test fixture. Derive a sane default
            // from the slug when none is given; an Owner/Admin can still
            // change it (via Settings) any time before it locks.
            if (blank($company->code) && filled($company->slug)) {
                $company->code = Str::of($company->slug)->upper()->replace('-', '')->substr(0, 10)->toString();
            }
        });
    }

    /** Companies that are not disabled — see docs/rebuild/specs/01-company-foundation/Specs.md "Reject unknown, disabled, or mismatched company contexts." */
    public function scopeActive($query)
    {
        // Table-qualified: this scope also runs through the `companies()`
        // BelongsToMany relation alongside `wherePivot('is_active', ...)`
        // on `company_user`, where an unqualified `is_active` is ambiguous.
        return $query->where('companies.is_active', true);
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

    public function taxSetting(): HasOne
    {
        return $this->hasOne(CompanyTaxSetting::class);
    }

    public function numberingSequences(): HasMany
    {
        return $this->hasMany(NumberingSequence::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function sourceRecords(): HasMany
    {
        return $this->hasMany(SourceRecord::class);
    }

    public function userInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(PaymentGateway::class);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * The logo as a base64 data URI, for embedding in a PDF — dompdf can't
     * reliably fetch a `Storage::url()` (the upload's disk is `local` by
     * default, not web-accessible), so this reads the file directly and
     * inlines it instead. Returns null (rather than throwing) when there's
     * no logo or the stored file is missing, so a PDF still renders fine
     * without one.
     */
    public function getLogoDataUri(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        try {
            $disk = Storage::disk(config('filesystems.default'));

            if (! $disk->exists($this->logo_path)) {
                return null;
            }

            $mimeType = $disk->mimeType($this->logo_path) ?: 'image/png';

            return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($this->logo_path));
        } catch (Throwable) {
            return null;
        }
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
