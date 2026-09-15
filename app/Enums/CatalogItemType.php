<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A catalog item's kind — `Product` (see docs/rebuild/specs/02-parties-and-catalog/Specs.md)
 * now models any sellable line a quotation/invoice can pull from, not just
 * physical goods: a service or labor line prices work rather than a good,
 * and `Other` covers a miscellaneous/custom line. See also
 * `App\Enums\TaxCategory` (the tax classification is a separate axis from
 * this type).
 */
enum CatalogItemType: string implements HasColor, HasLabel
{
    case Product = 'product';
    case Service = 'service';
    case Labor = 'labor';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Service => 'Service',
            self::Labor => 'Labor',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Product => 'info',
            self::Service => 'success',
            self::Labor => 'warning',
            self::Other => 'gray',
        };
    }
}
