<?php

namespace App\Enums;

/**
 * A catalog item's tax classification, per
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §3: Company B applies the approved
 * Indonesian PPN calculation only to `StandardTaxable` lines; `NonTaxable`
 * lines never get it. Company A calculates no new customer tax regardless of
 * this value. Other tax brackets are explicitly deferred — do not add more
 * cases without a change-control note (Specs.md §19).
 */
enum TaxCategory: string
{
    case StandardTaxable = 'standard_taxable';
    case NonTaxable = 'non_taxable';

    public function getLabel(): string
    {
        return match ($this) {
            self::StandardTaxable => 'Standard taxable',
            self::NonTaxable => 'Non-taxable',
        };
    }

    /**
     * A Filament-style semantic color, same convention as every status
     * enum's own getColor() (gray/info/success/warning/danger) — resolve
     * through App\Support\TallStack\StatusColor::map() before handing it
     * to <x-badge>, exactly like a lifecycle status. Taxable is
     * informational (blue), non-taxable is neutral (gray) — was
     * previously a hardcoded ternary duplicated at its one call site
     * (TallStackProducts).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::StandardTaxable => 'info',
            self::NonTaxable => 'gray',
        };
    }
}
