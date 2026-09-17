<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per company: whether it calculates tax at all, and the default
 * mode/rate/DPP-factor configuration for the ones that do. See
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §3 — Company A is non-tax,
 * Company B uses the approved 12% PPN / 11-12 DPP Nilai
 * Lain calculation. The calculation engine itself (App\Services\Tax\*)
 * lands in Phase 04; this table only scaffolds the per-company switch.
 */
#[Fillable([
    'company_id', 'tax_enabled', 'default_tax_mode',
    'standard_tax_rate', 'dpp_factor_numerator', 'dpp_factor_denominator',
    'report_config',
])]
class CompanyTaxSetting extends Model
{
    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'standard_tax_rate' => 'decimal:2',
            'dpp_factor_numerator' => 'integer',
            'dpp_factor_denominator' => 'integer',
            'report_config' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
