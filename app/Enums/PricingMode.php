<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * "Each quotation, invoice, and vendor bill selects one pricing mode:
 * tax-exclusive or tax-inclusive. Taxable lines within that document
 * cannot mix modes at launch." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §3. The actual derived-
 * counterpart tax computation against this mode is Phase 04 scope
 * (App\Services\TaxCalculationService); Phase 03 only records the
 * selection on `Quotation`.
 */
enum PricingMode: string implements HasLabel
{
    case Exclusive = 'exclusive';
    case Inclusive = 'inclusive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Exclusive => 'Tax-exclusive',
            self::Inclusive => 'Tax-inclusive',
        };
    }
}
