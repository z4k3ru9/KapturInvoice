<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A catalog item's tax classification, per
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §3: Axen applies the approved
 * Indonesian PPN calculation only to `StandardTaxable` lines; `NonTaxable`
 * lines never get it. Karunia calculates no new customer tax regardless of
 * this value. Other tax brackets are explicitly deferred — do not add more
 * cases without a change-control note (Specs.md §19).
 */
enum TaxCategory: string implements HasLabel
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
}
